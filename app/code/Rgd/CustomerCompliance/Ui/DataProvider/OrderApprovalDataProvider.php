<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Ui\DataProvider;

use Magento\Backend\Model\UrlInterface as BackendUrlInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Psr\Log\LoggerInterface;
use Rgd\CustomerCompliance\Api\FieldRepositoryInterface;
use Rgd\CustomerCompliance\Model\ResourceModel\Document\CollectionFactory as DocumentCollectionFactory;
use Rgd\CustomerCompliance\Model\ResourceModel\FieldValue\CollectionFactory as FieldValueCollectionFactory;
use Rgd\CustomerCompliance\Model\ResourceModel\OrderApproval\CollectionFactory;

/**
 * Ui/Component data provider backing the Order Approvals ("Pending Verification") grid and the
 * Approval Detail form.
 *
 * NOTE (performance): the grid needs order/customer display columns (order increment id,
 * customer email, customer group label) that don't live on the
 * rgd_customercompliance_order_approval table itself. For MVP simplicity this provider
 * post-processes each row by loading the related order via OrderRepositoryInterface, which is
 * an N+1 read per grid page (bounded by page size, so not unbounded, but still one extra query
 * per row). The same bounded-N+1 approach is used below to attach submitted field values and
 * document links. A production-grade implementation should instead add proper collection JOINs
 * in Model\ResourceModel\OrderApproval\Collection for a single query per grid load. Flagging
 * this as a Performance Testing stage follow-up per the Design doc.
 */
class OrderApprovalDataProvider extends AbstractDataProvider
{
    /**
     * @var array<int, string> field_id => label, cached per-request to bound repeated lookups
     *      when multiple rows/values reference the same field.
     */
    private array $fieldLabelCache = [];

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param GroupRepositoryInterface $groupRepository
     * @param FieldValueCollectionFactory $fieldValueCollectionFactory
     * @param DocumentCollectionFactory $documentCollectionFactory
     * @param FieldRepositoryInterface $fieldRepository
     * @param BackendUrlInterface $backendUrl
     * @param LoggerInterface $logger
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly FieldValueCollectionFactory $fieldValueCollectionFactory,
        private readonly DocumentCollectionFactory $documentCollectionFactory,
        private readonly FieldRepositoryInterface $fieldRepository,
        private readonly BackendUrlInterface $backendUrl,
        private readonly LoggerInterface $logger,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);

        $this->collection = $collectionFactory->create();
    }

    /**
     * @inheritDoc
     */
    public function getData(): array
    {
        $data = parent::getData();

        if (!empty($data['items'])) {
            foreach ($data['items'] as &$item) {
                $item = $this->addOrderDisplayFields($item);
                $item = $this->addComplianceDataFields($item);
            }
            unset($item);
        }

        return $data;
    }

    /**
     * Attach order-derived display columns (order #, customer email, group label) to a row.
     *
     * @param array $item
     * @return array
     */
    private function addOrderDisplayFields(array $item): array
    {
        $item['order_increment_id'] = '';
        $item['customer_email'] = '';
        $item['customer_group_label'] = '';

        if (empty($item['order_id'])) {
            return $item;
        }

        try {
            $order = $this->orderRepository->get((int)$item['order_id']);
            $item['order_increment_id'] = $order->getIncrementId();
            $item['customer_email'] = (string)$order->getCustomerEmail();

            $groupId = $order->getCustomerGroupId();
            if ($groupId !== null) {
                try {
                    $item['customer_group_label'] = $this->groupRepository->getById((int)$groupId)->getCode();
                } catch (NoSuchEntityException $e) {
                    // Group may have been deleted since the order was placed; leave label blank.
                    $this->logger->warning(
                        sprintf(
                            'Rgd_CustomerCompliance: customer group %d not found while building the'
                            . ' Order Approvals grid row for order %d.',
                            (int)$groupId,
                            (int)$item['order_id']
                        )
                    );
                }
            }
        } catch (NoSuchEntityException $e) {
            // Order may have been deleted; leave the display columns blank rather than fail the grid.
            $this->logger->warning(
                sprintf(
                    'Rgd_CustomerCompliance: order %d not found while building the Order Approvals grid row.',
                    (int)$item['order_id']
                )
            );
        }

        return $item;
    }

    /**
     * Attach submitted compliance field values and document download links for the customer.
     *
     * Resolves field labels via FieldRepositoryInterface. Fixes the previously-blank
     * "Submitted Field Values" / "Documents" fields on the Approval
     * Detail view (`view/adminhtml/ui_component/order_approval_form.xml`) - this data provider
     * class backs both that form and the listing grid, and this method is only meaningfully
     * consumed by the detail form, but running it for grid rows too is harmless (small, bounded
     * per-row cost, same as addOrderDisplayFields() above).
     *
     * @param array $item
     * @return array
     */
    private function addComplianceDataFields(array $item): array
    {
        $item['submitted_field_values_display'] = '';
        $item['documents_display'] = '';

        if (empty($item['customer_id'])) {
            return $item;
        }

        $customerId = (int)$item['customer_id'];

        $fieldValueLines = [];
        $fieldValueCollection = $this->fieldValueCollectionFactory->create();
        $fieldValueCollection->addFieldToFilter('customer_id', $customerId);

        foreach ($fieldValueCollection->getItems() as $fieldValue) {
            $value = $fieldValue->getValue();
            if ($value === null || $value === '') {
                // File-backed field values store the value in the linked document row instead
                // (see the "documents_display" block below), so there's nothing to show here.
                continue;
            }

            $label = $this->resolveFieldLabel((int)$fieldValue->getFieldId());
            $fieldValueLines[] = sprintf('%s: %s', $label, $value);
        }

        $item['submitted_field_values_display'] = implode("\n", $fieldValueLines);

        $documentLines = [];
        $documentCollection = $this->documentCollectionFactory->create();
        $documentCollection->addFieldToFilter('customer_id', $customerId);
        $documentCollection->addFieldToFilter('is_current', 1);

        foreach ($documentCollection->getItems() as $document) {
            $label = $this->resolveFieldLabel((int)$document->getFieldId());
            $url = $this->backendUrl->getUrl(
                'customercompliance/document/download',
                ['id' => $document->getDocumentId()]
            );
            $documentLines[] = sprintf('%s: %s (%s)', $label, $document->getFileName(), $url);
        }

        $item['documents_display'] = implode("\n", $documentLines);

        return $item;
    }

    /**
     * Resolve a field's label by id, caching within this request to bound repeated lookups.
     *
     * @param int $fieldId
     * @return string
     */
    private function resolveFieldLabel(int $fieldId): string
    {
        if (!isset($this->fieldLabelCache[$fieldId])) {
            try {
                $this->fieldLabelCache[$fieldId] = $this->fieldRepository->getById($fieldId)->getLabel();
            } catch (NoSuchEntityException $e) {
                $this->fieldLabelCache[$fieldId] = (string)__('(deleted field #%1)', $fieldId);
            }
        }

        return $this->fieldLabelCache[$fieldId];
    }
}
