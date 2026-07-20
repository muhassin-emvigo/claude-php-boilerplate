<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Api\Data;

/**
 * A compliance approval (verification hold) record for a single order.
 *
 * @api
 */
interface OrderApprovalInterface
{
    /**
     * Order is on hold pending manual compliance verification.
     */
    public const STATUS_PENDING_VERIFICATION = 'pending_verification';

    /**
     * Order has been approved.
     */
    public const STATUS_APPROVED = 'approved';

    /**
     * Order has been rejected.
     */
    public const STATUS_REJECTED = 'rejected';

    /**
     * No refund is applicable.
     */
    public const REFUND_STATUS_NONE = 'none';

    /**
     * A refund has been initiated and is pending completion.
     */
    public const REFUND_STATUS_PENDING = 'pending';

    /**
     * The refund completed successfully.
     */
    public const REFUND_STATUS_COMPLETED = 'completed';

    /**
     * The refund attempt failed.
     */
    public const REFUND_STATUS_FAILED = 'failed';

    /**
     * The refund could not be completed automatically and fell back to an offline process.
     */
    public const REFUND_STATUS_OFFLINE_FALLBACK = 'offline_fallback';

    /**
     * Get the approval id.
     *
     * @return int|null
     */
    public function getApprovalId(): ?int;

    /**
     * Set the approval id.
     *
     * @param int|null $approvalId
     * @return $this
     */
    public function setApprovalId(?int $approvalId): self;

    /**
     * Get the order id this approval record belongs to.
     *
     * @return int
     */
    public function getOrderId(): int;

    /**
     * Set the order id this approval record belongs to.
     *
     * @param int $orderId
     * @return $this
     */
    public function setOrderId(int $orderId): self;

    /**
     * Get the customer id who placed the order.
     *
     * @return int
     */
    public function getCustomerId(): int;

    /**
     * Set the customer id who placed the order.
     *
     * @param int $customerId
     * @return $this
     */
    public function setCustomerId(int $customerId): self;

    /**
     * Get the approval status. One of the STATUS_* constants.
     *
     * @return string
     */
    public function getStatus(): string;

    /**
     * Set the approval status. One of the STATUS_* constants.
     *
     * @param string $status
     * @return $this
     */
    public function setStatus(string $status): self;

    /**
     * Get the id of the admin user who reviewed this approval, if any.
     *
     * @return int|null
     */
    public function getReviewerAdminId(): ?int;

    /**
     * Set the id of the admin user who reviewed this approval, if any.
     *
     * @param int|null $reviewerAdminId
     * @return $this
     */
    public function setReviewerAdminId(?int $reviewerAdminId): self;

    /**
     * Get the reviewer's decision notes.
     *
     * @return string|null
     */
    public function getDecisionNotes(): ?string;

    /**
     * Set the reviewer's decision notes.
     *
     * @param string|null $decisionNotes
     * @return $this
     */
    public function setDecisionNotes(?string $decisionNotes): self;

    /**
     * Get the timestamp the decision was made at.
     *
     * @return string|null
     */
    public function getDecisionAt(): ?string;

    /**
     * Set the timestamp the decision was made at.
     *
     * @param string|null $decisionAt
     * @return $this
     */
    public function setDecisionAt(?string $decisionAt): self;

    /**
     * Get the refund status. One of the REFUND_STATUS_* constants.
     *
     * @return string|null
     */
    public function getRefundStatus(): ?string;

    /**
     * Set the refund status. One of the REFUND_STATUS_* constants.
     *
     * @param string|null $refundStatus
     * @return $this
     */
    public function setRefundStatus(?string $refundStatus): self;

    /**
     * Get the refund gateway/transaction reference.
     *
     * @return string|null
     */
    public function getRefundReference(): ?string;

    /**
     * Set the refund gateway/transaction reference.
     *
     * @param string|null $refundReference
     * @return $this
     */
    public function setRefundReference(?string $refundReference): self;

    /**
     * Get the number of times documents have been resubmitted against this approval.
     *
     * @return int
     */
    public function getResubmissionCount(): int;

    /**
     * Set the number of times documents have been resubmitted against this approval.
     *
     * @param int $resubmissionCount
     * @return $this
     */
    public function setResubmissionCount(int $resubmissionCount): self;
}
