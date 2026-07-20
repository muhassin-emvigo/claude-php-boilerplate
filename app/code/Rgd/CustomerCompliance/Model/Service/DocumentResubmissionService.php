<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model\Service;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Rgd\CustomerCompliance\Api\AuditLoggerInterface;
use Rgd\CustomerCompliance\Api\Data\FieldInterface;
use Rgd\CustomerCompliance\Api\Data\OrderApprovalInterface;
use Rgd\CustomerCompliance\Api\DocumentResubmissionServiceInterface;
use Rgd\CustomerCompliance\Api\DocumentStorageServiceInterface;
use Rgd\CustomerCompliance\Api\FieldRepositoryInterface;
use Rgd\CustomerCompliance\Model\OrderApprovalRepository;

/**
 * Handles resubmission of compliance documents against a rejected order.
 */
class DocumentResubmissionService implements DocumentResubmissionServiceInterface
{
    /**
     * @param OrderApprovalRepository $orderApprovalRepository
     * @param DocumentStorageServiceInterface $documentStorageService
     * @param AuditLoggerInterface $auditLogger
     * @param FieldRepositoryInterface $fieldRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param UploadedFileNormalizer $uploadedFileNormalizer
     */
    public function __construct(
        private readonly OrderApprovalRepository $orderApprovalRepository,
        private readonly DocumentStorageServiceInterface $documentStorageService,
        private readonly AuditLoggerInterface $auditLogger,
        private readonly FieldRepositoryInterface $fieldRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly UploadedFileNormalizer $uploadedFileNormalizer
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resubmit(int $orderId, array $uploadedFiles): void
    {
        $approval = $this->orderApprovalRepository->getByOrderId($orderId);

        if ($approval->getStatus() !== OrderApprovalInterface::STATUS_REJECTED) {
            throw new StateException(__('Documents can only be resubmitted for a rejected order.'));
        }

        foreach ($uploadedFiles as $fieldCode => $rawFile) {
            $field = $this->getFieldByCode((string)$fieldCode);
            $uploadedFile = $this->uploadedFileNormalizer->normalize((string)$fieldCode, $rawFile);

            // store() already handles version incrementing and marking prior documents for
            // this customer/field/order as no-longer-current.
            $this->documentStorageService->store(
                $approval->getCustomerId(),
                (int)$field->getFieldId(),
                $orderId,
                $uploadedFile
            );
        }

        // decision_notes/decision_at from the prior rejection are intentionally left as-is:
        // they document why the PRIOR decision was made and remain useful context for the
        // next reviewer. They will be overwritten naturally when a new decision is recorded.
        $approval->setStatus(OrderApprovalInterface::STATUS_PENDING_VERIFICATION);
        $approval->setResubmissionCount($approval->getResubmissionCount() + 1);
        $this->orderApprovalRepository->save($approval);

        $this->auditLogger->log(
            'customer',
            $approval->getCustomerId(),
            'resubmission_submitted',
            'order_approval',
            (string)$approval->getApprovalId(),
            null
        );
    }

    /**
     * Resolve a compliance field by its unique field code.
     *
     * @param string $fieldCode
     * @return FieldInterface
     * @throws NoSuchEntityException
     */
    private function getFieldByCode(string $fieldCode): FieldInterface
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('field_code', $fieldCode)
            ->create();

        $items = $this->fieldRepository->getList($searchCriteria)->getItems();
        $field = reset($items);

        if (!$field instanceof FieldInterface) {
            throw new NoSuchEntityException(__('No compliance field exists with code "%1".', $fieldCode));
        }

        return $field;
    }
}
