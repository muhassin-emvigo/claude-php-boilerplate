<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Ui\Component\Listing\Column;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Rgd\CustomerCompliance\Api\Data\OrderApprovalInterface;

/**
 * Row actions renderer for the Order Approvals ("Pending Verification") grid.
 *
 * Per the Design doc's decision, inline reject is not offered directly on the grid — the
 * "Review/Reject" row action instead links through to the detail page (order_approval_form /
 * Approval\View controller), where the reviewer supplies mandatory decision notes before
 * rejecting. "Approve" is offered inline for the confirm-free quick-approve flow, and is also
 * available as a bulk massaction (see order_approval_listing.xml).
 *
 * NOTE (approximation): Controller\Adminhtml\Approval\Approve implements
 * HttpPostActionInterface, so it only accepts POST requests. The grid actions column mechanism
 * used here renders plain anchor hrefs (a GET navigation), which is the standard pattern for
 * Magento Ui/Component row actions and is what's used below for consistency with the rest of the
 * grid — but that means, as coded, clicking "Approve" from the grid would need the front-end
 * mixin/controller wiring to submit as POST (e.g. the same confirm+ajax-post mechanism the grid's
 * "delete" action type uses) rather than a bare link navigation, or the controller would need to
 * additionally accept GET for this single-row convenience action. Flagging this as a follow-up
 * for whoever wires the final front-end behavior/JS mixins for this grid; the bulk massaction
 * path (submitted as a real POST by Magento_Ui/js/grid/massactions) does not have this problem.
 */
class OrderApprovalActions extends Column
{
    private const URL_PATH_APPROVE = 'customercompliance/approval/approve';
    private const URL_PATH_VIEW = 'customercompliance/approval/view';

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param AuthorizationInterface $authorization
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly AuthorizationInterface $authorization,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @inheritDoc
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $name = $this->getData('name');

        foreach ($dataSource['data']['items'] as & $item) {
            if (!isset($item['approval_id'])) {
                continue;
            }

            $isPending = ($item['status'] ?? null) === OrderApprovalInterface::STATUS_PENDING_VERIFICATION;

            if ($isPending && $this->authorization->isAllowed('Rgd_CustomerCompliance::approve')) {
                $item[$name]['approve'] = [
                    'href' => $this->urlBuilder->getUrl(
                        self::URL_PATH_APPROVE,
                        ['approval_id' => $item['approval_id']]
                    ),
                    'label' => __('Approve'),
                    // Confirm-free per the Design doc's inline-approve decision.
                ];
            }

            if ($this->authorization->isAllowed('Rgd_CustomerCompliance::approvals')) {
                $item[$name]['view'] = [
                    'href' => $this->urlBuilder->getUrl(
                        self::URL_PATH_VIEW,
                        ['approval_id' => $item['approval_id']]
                    ),
                    'label' => $isPending ? __('Review/Reject') : __('View'),
                ];
            }
        }

        return $dataSource;
    }
}
