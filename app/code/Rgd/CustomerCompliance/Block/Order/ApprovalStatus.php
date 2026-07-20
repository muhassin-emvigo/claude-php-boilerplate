<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Block\Order;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Rgd\CustomerCompliance\Api\Data\OrderApprovalInterface;
use Rgd\CustomerCompliance\Api\OrderApprovalRepositoryInterface;

/**
 * Shows the compliance approval status (and, for rejected orders, the rejection reason) on the
 * customer-facing single order view page. Per the NEW stakeholder confirmation, the rejection
 * reason must be visible here directly, not only in the dedicated resubmit flow.
 *
 * If no approval record exists for the order, this order never needed compliance review; the
 * template renders nothing in that case.
 */
class ApprovalStatus extends Template
{
    /**
     * @param Context $context
     * @param OrderApprovalRepositoryInterface $orderApprovalRepository
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly OrderApprovalRepositoryInterface $orderApprovalRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get the approval record for the given order, or null if none exists.
     *
     * @param int|null $orderId
     * @return OrderApprovalInterface|null
     */
    public function getApproval(?int $orderId): ?OrderApprovalInterface
    {
        if ($orderId === null || $orderId <= 0) {
            return null;
        }

        try {
            return $this->orderApprovalRepository->getByOrderId($orderId);
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * Get the current order id from the parent order-view block, if available.
     *
     * @return int|null
     */
    public function getCurrentOrderId(): ?int
    {
        $order = $this->getOrder();
        if ($order === null) {
            return null;
        }

        $orderId = $order->getId();

        return $orderId !== null ? (int)$orderId : null;
    }

    /**
     * Get the current order from the `order_id` layout argument or the parent order view block.
     *
     * @return \Magento\Sales\Model\Order|null
     */
    public function getOrder()
    {
        $viewBlock = $this->getLayout()->getBlock('sales.order.info');
        if ($viewBlock !== false && method_exists($viewBlock, 'getOrder')) {
            return $viewBlock->getOrder();
        }

        return null;
    }

    /**
     * Build the URL to the resubmission form for a given order.
     *
     * @param int $orderId
     * @return string
     */
    public function getResubmitUrl(int $orderId): string
    {
        return $this->getUrl('customercompliance/account/resubmit', ['order_id' => $orderId]);
    }
}
