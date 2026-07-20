<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model;

use Magento\Framework\Model\AbstractModel;
use Rgd\CustomerCompliance\Api\Data\OrderApprovalInterface;
use Rgd\CustomerCompliance\Model\ResourceModel\OrderApproval as OrderApprovalResourceModel;

/**
 * Order approval (compliance hold) model.
 */
class OrderApproval extends AbstractModel implements OrderApprovalInterface
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(OrderApprovalResourceModel::class);
    }

    /**
     * @inheritDoc
     */
    public function getApprovalId(): ?int
    {
        $value = $this->getData('approval_id');

        return $value !== null ? (int)$value : null;
    }

    /**
     * @inheritDoc
     */
    public function setApprovalId(?int $approvalId): OrderApprovalInterface
    {
        return $this->setData('approval_id', $approvalId);
    }

    /**
     * @inheritDoc
     */
    public function getOrderId(): int
    {
        return (int)$this->getData('order_id');
    }

    /**
     * @inheritDoc
     */
    public function setOrderId(int $orderId): OrderApprovalInterface
    {
        return $this->setData('order_id', $orderId);
    }

    /**
     * @inheritDoc
     */
    public function getCustomerId(): int
    {
        return (int)$this->getData('customer_id');
    }

    /**
     * @inheritDoc
     */
    public function setCustomerId(int $customerId): OrderApprovalInterface
    {
        return $this->setData('customer_id', $customerId);
    }

    /**
     * @inheritDoc
     */
    public function getStatus(): string
    {
        return (string)$this->getData('status');
    }

    /**
     * @inheritDoc
     */
    public function setStatus(string $status): OrderApprovalInterface
    {
        return $this->setData('status', $status);
    }

    /**
     * @inheritDoc
     */
    public function getReviewerAdminId(): ?int
    {
        $value = $this->getData('reviewer_admin_id');

        return $value !== null ? (int)$value : null;
    }

    /**
     * @inheritDoc
     */
    public function setReviewerAdminId(?int $reviewerAdminId): OrderApprovalInterface
    {
        return $this->setData('reviewer_admin_id', $reviewerAdminId);
    }

    /**
     * @inheritDoc
     */
    public function getDecisionNotes(): ?string
    {
        $value = $this->getData('decision_notes');

        return $value !== null ? (string)$value : null;
    }

    /**
     * @inheritDoc
     */
    public function setDecisionNotes(?string $decisionNotes): OrderApprovalInterface
    {
        return $this->setData('decision_notes', $decisionNotes);
    }

    /**
     * @inheritDoc
     */
    public function getDecisionAt(): ?string
    {
        $value = $this->getData('decision_at');

        return $value !== null ? (string)$value : null;
    }

    /**
     * @inheritDoc
     */
    public function setDecisionAt(?string $decisionAt): OrderApprovalInterface
    {
        return $this->setData('decision_at', $decisionAt);
    }

    /**
     * @inheritDoc
     */
    public function getRefundStatus(): ?string
    {
        $value = $this->getData('refund_status');

        return $value !== null ? (string)$value : null;
    }

    /**
     * @inheritDoc
     */
    public function setRefundStatus(?string $refundStatus): OrderApprovalInterface
    {
        return $this->setData('refund_status', $refundStatus);
    }

    /**
     * @inheritDoc
     */
    public function getRefundReference(): ?string
    {
        $value = $this->getData('refund_reference');

        return $value !== null ? (string)$value : null;
    }

    /**
     * @inheritDoc
     */
    public function setRefundReference(?string $refundReference): OrderApprovalInterface
    {
        return $this->setData('refund_reference', $refundReference);
    }

    /**
     * @inheritDoc
     */
    public function getResubmissionCount(): int
    {
        return (int)$this->getData('resubmission_count');
    }

    /**
     * @inheritDoc
     */
    public function setResubmissionCount(int $resubmissionCount): OrderApprovalInterface
    {
        return $this->setData('resubmission_count', $resubmissionCount);
    }
}
