<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Block\Adminhtml\Approval\View;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Registry;
use Rgd\CustomerCompliance\Api\Data\OrderApprovalInterface;

/**
 * Shared helpers for Order Approval detail-page ui_component form buttons.
 *
 * Mirrors Block\Adminhtml\GroupConfig\Edit\GenericButton's shape (request-param helper +
 * getUrl()), plus a getApproval() helper reading the approval record that
 * Controller\Adminhtml\Approval\View registers into the registry, so buttons can
 * conditionally render only when relevant to the record's current status.
 */
class GenericButton
{
    private const REGISTRY_KEY_APPROVAL = 'rgd_customercompliance_approval';

    /**
     * @param Context $context
     * @param Registry $registry
     */
    public function __construct(
        protected readonly Context $context,
        protected readonly Registry $registry
    ) {
    }

    /**
     * Get the current approval_id from the request, if any.
     *
     * @return int|null
     */
    public function getApprovalId(): ?int
    {
        $approvalId = $this->context->getRequest()->getParam('approval_id');

        return $approvalId ? (int)$approvalId : null;
    }

    /**
     * Get the approval record registered by Controller\Adminhtml\Approval\View, if any.
     *
     * @return OrderApprovalInterface|null
     */
    public function getApproval(): ?OrderApprovalInterface
    {
        $approval = $this->registry->registry(self::REGISTRY_KEY_APPROVAL);

        return $approval instanceof OrderApprovalInterface ? $approval : null;
    }

    /**
     * Whether the registered approval record is still pending manual verification.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        $approval = $this->getApproval();

        return $approval !== null && $approval->getStatus() === OrderApprovalInterface::STATUS_PENDING_VERIFICATION;
    }

    /**
     * Whether the registered approval record's refund is eligible for a retry.
     *
     * True when the refund previously failed.
     *
     * @return bool
     */
    public function isRefundRetryable(): bool
    {
        $approval = $this->getApproval();

        return $approval !== null && $approval->getRefundStatus() === OrderApprovalInterface::REFUND_STATUS_FAILED;
    }

    /**
     * Build an admin URL.
     *
     * @param string $route
     * @param array $params
     * @return string
     */
    public function getUrl(string $route = '*/*/', array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
