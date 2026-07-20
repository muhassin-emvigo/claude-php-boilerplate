<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Plugin\Sales;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\InvoiceCommentCreationInterface;
use Magento\Sales\Api\Data\InvoiceCreationArgumentsInterface;
use Magento\Sales\Api\InvoiceOrderInterface;
use Rgd\CustomerCompliance\Api\Data\OrderApprovalInterface;
use Rgd\CustomerCompliance\Api\OrderApprovalRepositoryInterface;

/**
 * Before-plugin on invoice creation that blocks invoicing orders which are still pending
 * (or have failed) compliance verification.
 */
class BlockInvoicePlugin
{
    /**
     * @param OrderApprovalRepositoryInterface $orderApprovalRepository
     */
    public function __construct(
        private readonly OrderApprovalRepositoryInterface $orderApprovalRepository
    ) {
    }

    /**
     * Guard invoice creation on the linked order's compliance approval status.
     *
     * @param InvoiceOrderInterface $subject
     * @param int $orderId
     * @param bool $capture
     * @param array $items
     * @param bool $notify
     * @param bool $appendComment
     * @param InvoiceCommentCreationInterface|null $comment
     * @param InvoiceCreationArgumentsInterface|null $arguments
     * @return array
     * @throws LocalizedException
     */
    public function beforeExecute(
        InvoiceOrderInterface $subject,
        int $orderId,
        bool $capture = false,
        array $items = [],
        bool $notify = false,
        bool $appendComment = false,
        ?InvoiceCommentCreationInterface $comment = null,
        ?InvoiceCreationArgumentsInterface $arguments = null
    ): array {
        try {
            $approval = $this->orderApprovalRepository->getByOrderId($orderId);
        } catch (NoSuchEntityException $e) {
            // No approval record exists for this order: there is nothing to gate on, allow it.
            return [$orderId, $capture, $items, $notify, $appendComment, $comment, $arguments];
        }

        if ($approval->getStatus() !== OrderApprovalInterface::STATUS_APPROVED) {
            throw new LocalizedException(
                __('This order cannot be invoiced until compliance verification is approved.')
            );
        }

        return [$orderId, $capture, $items, $notify, $appendComment, $comment, $arguments];
    }
}
