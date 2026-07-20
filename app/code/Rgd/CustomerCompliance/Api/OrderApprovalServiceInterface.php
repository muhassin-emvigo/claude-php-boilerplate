<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Api;

/**
 * Orchestrates the order-level compliance approval workflow.
 *
 * @api
 */
interface OrderApprovalServiceInterface
{
    /**
     * Place an order on hold pending manual compliance verification.
     *
     * @param int $orderId
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function holdForVerification(int $orderId): void;

    /**
     * Approve a pending order approval record.
     *
     * @param int $approvalId
     * @param int $adminUserId
     * @param string|null $notes
     * @return \Rgd\CustomerCompliance\Api\Data\OrderApprovalInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Rgd\CustomerCompliance\Exception\BusinessRuleException
     */
    public function approve(int $approvalId, int $adminUserId, ?string $notes): Data\OrderApprovalInterface;

    /**
     * Reject a pending order approval record.
     *
     * @param int $approvalId
     * @param int $adminUserId
     * @param string $notes
     * @return \Rgd\CustomerCompliance\Api\Data\OrderApprovalInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Rgd\CustomerCompliance\Exception\BusinessRuleException
     */
    public function reject(int $approvalId, int $adminUserId, string $notes): Data\OrderApprovalInterface;

    /**
     * Retry a previously failed refund for a rejected order approval.
     *
     * @param int $approvalId
     * @return \Rgd\CustomerCompliance\Api\Data\OrderApprovalInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Rgd\CustomerCompliance\Exception\BusinessRuleException
     */
    public function retryRefund(int $approvalId): Data\OrderApprovalInterface;
}
