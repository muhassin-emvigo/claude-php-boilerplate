<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model\Service;

use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Rgd\CustomerCompliance\Api\AuditLoggerInterface;
use Rgd\CustomerCompliance\Api\Data;
use Rgd\CustomerCompliance\Api\Data\OrderApprovalInterface;
use Rgd\CustomerCompliance\Api\Data\RefundResultInterface;
use Rgd\CustomerCompliance\Api\GroupConfigRepositoryInterface;
use Rgd\CustomerCompliance\Api\OrderApprovalServiceInterface;
use Rgd\CustomerCompliance\Api\OrderRefundServiceInterface;
use Rgd\CustomerCompliance\Exception\BusinessRuleException;
use Rgd\CustomerCompliance\Model\OrderApprovalFactory;
use Rgd\CustomerCompliance\Model\OrderApprovalRepository;

/**
 * Orchestrates the order-level compliance approval workflow.
 */
class OrderApprovalService implements OrderApprovalServiceInterface
{
    private const EMAIL_TEMPLATE_ORDER_APPROVED = 'customercompliance_order_approved';

    private const EMAIL_TEMPLATE_ORDER_REJECTED = 'customercompliance_order_rejected';

    private const ORDER_STATE_PENDING_VERIFICATION = 'pending_verification';

    private const ORDER_STATUS_PENDING_VERIFICATION = 'pending_verification';

    /**
     * @param OrderApprovalRepository $orderApprovalRepository
     * @param OrderApprovalFactory $orderApprovalFactory
     * @param GroupConfigRepositoryInterface $groupConfigRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param AuditLoggerInterface $auditLogger
     * @param OrderRefundServiceInterface $orderRefundService
     * @param TransportBuilder $transportBuilder
     * @param ManagerInterface $eventManager
     * @param LoggerInterface $logger
     * @param ResourceConnection $resourceConnection
     * @param DateTime $dateTime
     * @param StoreManagerInterface $storeManager
     * @param DataObjectFactory $dataObjectFactory
     */
    public function __construct(
        private readonly OrderApprovalRepository $orderApprovalRepository,
        private readonly OrderApprovalFactory $orderApprovalFactory,
        private readonly GroupConfigRepositoryInterface $groupConfigRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly AuditLoggerInterface $auditLogger,
        private readonly OrderRefundServiceInterface $orderRefundService,
        private readonly TransportBuilder $transportBuilder,
        private readonly ManagerInterface $eventManager,
        private readonly LoggerInterface $logger,
        private readonly ResourceConnection $resourceConnection,
        private readonly DateTime $dateTime,
        private readonly StoreManagerInterface $storeManager,
        private readonly DataObjectFactory $dataObjectFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function holdForVerification(int $orderId): void
    {
        $order = $this->orderRepository->get($orderId);

        // Magento\Sales\Model\Order exposes customer_group_id (captured at order time) even
        // though it is not declared on the Sales\Api\Data\OrderInterface contract.
        $customerGroupId = (int)$order->getData('customer_group_id');

        try {
            $groupConfig = $this->groupConfigRepository->getByCustomerGroupId($customerGroupId);
        } catch (NoSuchEntityException $e) {
            // No configuration for this group means no approval gate applies.
            return;
        }

        if (!$groupConfig->isApprovalRequired()) {
            return;
        }

        try {
            // Idempotent: the plugin that calls this may fire more than once for the same
            // order (unique(order_id) at the DB layer is the ultimate guard).
            $this->orderApprovalRepository->getByOrderId($orderId);

            return;
        } catch (NoSuchEntityException $e) {
            // No existing hold - proceed to create one.
            unset($e);
        }

        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();

        try {
            /** @var OrderApprovalInterface $approval */
            $approval = $this->orderApprovalFactory->create();
            $approval->setOrderId($orderId)
                ->setCustomerId((int)($order->getCustomerId() ?? 0))
                ->setStatus(OrderApprovalInterface::STATUS_PENDING_VERIFICATION)
                ->setRefundStatus(OrderApprovalInterface::REFUND_STATUS_NONE)
                ->setResubmissionCount(0);
            $this->orderApprovalRepository->save($approval);

            $order->setState(self::ORDER_STATE_PENDING_VERIFICATION);
            $order->setStatus(self::ORDER_STATUS_PENDING_VERIFICATION);
            $this->orderRepository->save($order);

            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        $this->auditLogger->log('system', null, 'order_held', 'order_approval', (string)$orderId, null);
    }

    /**
     * @inheritDoc
     */
    public function approve(int $approvalId, int $adminUserId, ?string $notes): Data\OrderApprovalInterface
    {
        $approval = $this->orderApprovalRepository->getById($approvalId);
        $this->assertPendingVerification($approval);

        $approval->setStatus(OrderApprovalInterface::STATUS_APPROVED)
            ->setReviewerAdminId($adminUserId)
            ->setDecisionNotes($notes)
            ->setDecisionAt($this->dateTime->gmtDate());
        $approval = $this->orderApprovalRepository->save($approval);

        $order = $this->orderRepository->get($approval->getOrderId());
        $order->setState(Order::STATE_NEW);
        $order->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_NEW));
        $this->orderRepository->save($order);

        $this->eventManager->dispatch('rgd_cc_order_approved', ['order_approval' => $approval, 'order' => $order]);
        $this->auditLogger->log('admin', $adminUserId, 'order_approved', 'order_approval', (string)$approvalId, $notes);

        $this->sendOrderApprovedEmail($order);

        return $approval;
    }

    /**
     * @inheritDoc
     */
    public function reject(int $approvalId, int $adminUserId, string $notes): Data\OrderApprovalInterface
    {
        $approval = $this->orderApprovalRepository->getById($approvalId);
        $this->assertPendingVerification($approval);

        if (trim($notes) === '') {
            throw new BusinessRuleException(__('Rejection notes are required.'));
        }

        // Reject persists first; the event dispatch and refund attempt follow. A refund
        // failure must not roll back or hide the fact that the rejection itself succeeded.
        $approval->setStatus(OrderApprovalInterface::STATUS_REJECTED)
            ->setReviewerAdminId($adminUserId)
            ->setDecisionNotes($notes)
            ->setDecisionAt($this->dateTime->gmtDate());
        $approval = $this->orderApprovalRepository->save($approval);

        $order = $this->orderRepository->get($approval->getOrderId());

        $this->eventManager->dispatch('rgd_cc_order_rejected', ['order_approval' => $approval, 'order' => $order]);
        $this->auditLogger->log('admin', $adminUserId, 'order_rejected', 'order_approval', (string)$approvalId, $notes);

        $approval = $this->attemptRefund($approval, $order, $notes);

        $this->sendOrderRejectedEmail($order, $notes);

        return $approval;
    }

    /**
     * @inheritDoc
     */
    public function retryRefund(int $approvalId): Data\OrderApprovalInterface
    {
        $approval = $this->orderApprovalRepository->getById($approvalId);

        $retryableStatuses = [
            OrderApprovalInterface::REFUND_STATUS_FAILED,
            OrderApprovalInterface::REFUND_STATUS_PENDING,
        ];
        if (!in_array($approval->getRefundStatus(), $retryableStatuses, true)) {
            throw new StateException(__('This order approval is not eligible for a refund retry.'));
        }

        $order = $this->orderRepository->get($approval->getOrderId());
        $reason = (string)($approval->getDecisionNotes() ?? '');

        $approval = $this->attemptRefund($approval, $order, $reason);

        $this->auditLogger->log('system', null, 'refund_retry_attempted', 'order_approval', (string)$approvalId, null);

        return $approval;
    }

    /**
     * Guard: an approval must be pending verification to be approved/rejected.
     *
     * @param OrderApprovalInterface $approval
     * @return void
     * @throws StateException
     */
    private function assertPendingVerification(OrderApprovalInterface $approval): void
    {
        if ($approval->getStatus() !== OrderApprovalInterface::STATUS_PENDING_VERIFICATION) {
            throw new StateException(__('This order is not pending verification.'));
        }
    }

    /**
     * Attempt a refund for a rejected approval, updating its refund_status/refund_reference.
     *
     * A refund failure is captured on the approval record and audit-logged, never rethrown.
     *
     * @param OrderApprovalInterface $approval
     * @param OrderInterface $order
     * @param string $reason
     * @return OrderApprovalInterface
     */
    private function attemptRefund(
        OrderApprovalInterface $approval,
        OrderInterface $order,
        string $reason
    ): OrderApprovalInterface {
        try {
            $refundResult = $this->orderRefundService->refund(
                $approval->getOrderId(),
                (float)$order->getGrandTotal(),
                $reason
            );
            $approval->setRefundStatus($this->mapRefundStatus($refundResult->getStatus()));
            $approval->setRefundReference($refundResult->getReference());
        } catch (\Throwable $e) {
            $this->logger->error(
                'Refund attempt failed for order approval "' . $approval->getApprovalId() . '": ' . $e->getMessage(),
                ['exception' => $e]
            );
            $approval->setRefundStatus(OrderApprovalInterface::REFUND_STATUS_FAILED);
            $this->auditLogger->log(
                'system',
                null,
                'refund_failed',
                'order_approval',
                (string)$approval->getApprovalId(),
                $e->getMessage()
            );
        }

        return $this->orderApprovalRepository->save($approval);
    }

    /**
     * Map a RefundResultInterface::STATUS_* value onto an OrderApprovalInterface::REFUND_STATUS_* value.
     *
     * @param string $refundResultStatus
     * @return string
     */
    private function mapRefundStatus(string $refundResultStatus): string
    {
        return match ($refundResultStatus) {
            RefundResultInterface::STATUS_COMPLETED => OrderApprovalInterface::REFUND_STATUS_COMPLETED,
            RefundResultInterface::STATUS_MANUAL_FALLBACK => OrderApprovalInterface::REFUND_STATUS_OFFLINE_FALLBACK,
            default => OrderApprovalInterface::REFUND_STATUS_FAILED,
        };
    }

    /**
     * Best-effort "order approved" notification.
     *
     * @param OrderInterface $order
     * @return void
     */
    private function sendOrderApprovedEmail(OrderInterface $order): void
    {
        try {
            $storeId = (int)$this->storeManager->getStore()->getId();

            $transport = $this->transportBuilder
                ->setTemplateIdentifier(self::EMAIL_TEMPLATE_ORDER_APPROVED)
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
                ->setTemplateVars([
                    'customer' => $this->dataObjectFactory->create([
                        'data' => ['name' => $this->getOrderCustomerName($order)],
                    ]),
                    'order' => $order,
                    'store' => $this->storeManager->getStore(),
                ])
                ->setFrom('general')
                ->addTo((string)$order->getCustomerEmail(), $this->getOrderCustomerName($order))
                ->getTransport();

            $transport->sendMessage();
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send order-approved email: ' . $e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * Best-effort "order rejected" notification.
     *
     * Includes the reviewer's decision notes as the rejection reason, per the stakeholder
     * requirement that the customer must see it.
     *
     * @param OrderInterface $order
     * @param string $notes
     * @return void
     */
    private function sendOrderRejectedEmail(OrderInterface $order, string $notes): void
    {
        try {
            $storeId = (int)$this->storeManager->getStore()->getId();

            $transport = $this->transportBuilder
                ->setTemplateIdentifier(self::EMAIL_TEMPLATE_ORDER_REJECTED)
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
                ->setTemplateVars([
                    'customer' => $this->dataObjectFactory->create([
                        'data' => ['name' => $this->getOrderCustomerName($order)],
                    ]),
                    'order' => $order,
                    'decision' => $this->dataObjectFactory->create(['data' => ['notes' => $notes]]),
                    'store' => $this->storeManager->getStore(),
                ])
                ->setFrom('general')
                ->addTo((string)$order->getCustomerEmail(), $this->getOrderCustomerName($order))
                ->getTransport();

            $transport->sendMessage();
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send order-rejected email: ' . $e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * Resolve the order's customer display name.
     *
     * @param OrderInterface $order
     * @return string
     */
    private function getOrderCustomerName(OrderInterface $order): string
    {
        return trim((string)$order->getCustomerFirstname() . ' ' . (string)$order->getCustomerLastname());
    }
}
