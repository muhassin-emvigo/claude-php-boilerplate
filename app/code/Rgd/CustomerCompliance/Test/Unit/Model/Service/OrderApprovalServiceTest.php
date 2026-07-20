<?php

// NOTE: Written in parallel with Model\Service\OrderApprovalService. Constructor argument order
// below is a best-effort reconstruction from the shared Eng spec/interface list. Re-run and
// adjust (constructor arg order, exact retryable refund_status set, event payload shape, etc.)
// against the actual implementation during Build-stage integration.

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Test\Unit\Model\Service;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\State\StateException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Rgd\CustomerCompliance\Api\AuditLoggerInterface;
use Rgd\CustomerCompliance\Api\Data\GroupConfigInterface;
use Rgd\CustomerCompliance\Api\Data\OrderApprovalInterface;
use Rgd\CustomerCompliance\Api\GroupConfigRepositoryInterface;
use Rgd\CustomerCompliance\Api\OrderRefundServiceInterface;
use Rgd\CustomerCompliance\Exception\BusinessRuleException;
use Rgd\CustomerCompliance\Model\OrderApprovalFactory;
use Rgd\CustomerCompliance\Model\OrderApprovalRepository;
use Rgd\CustomerCompliance\Model\Service\OrderApprovalService;

/**
 * @covers \Rgd\CustomerCompliance\Model\Service\OrderApprovalService
 */
class OrderApprovalServiceTest extends TestCase
{
    // NOTE (fixed): the real constructor type-hints the CONCRETE OrderApprovalRepository class,
    // not OrderApprovalRepositoryInterface - mocking the interface alone does not satisfy that
    // parameter type (see the identical fix in DocumentResubmissionServiceTest).
    private OrderApprovalRepository&MockObject $orderApprovalRepository;
    private OrderApprovalFactory&MockObject $orderApprovalFactory;
    private GroupConfigRepositoryInterface&MockObject $groupConfigRepository;
    private OrderRepositoryInterface&MockObject $orderRepository;
    private AuditLoggerInterface&MockObject $auditLogger;
    private OrderRefundServiceInterface&MockObject $orderRefundService;
    private TransportBuilder&MockObject $transportBuilder;
    private ManagerInterface&MockObject $eventManager;
    private LoggerInterface&MockObject $logger;
    private ResourceConnection&MockObject $resourceConnection;
    private DateTime&MockObject $dateTime;
    private StoreManagerInterface&MockObject $storeManager;
    private DataObjectFactory&MockObject $dataObjectFactory;

    protected function setUp(): void
    {
        $this->orderApprovalRepository = $this->createMock(OrderApprovalRepository::class);
        $this->orderApprovalFactory = $this->createMock(OrderApprovalFactory::class);
        $this->groupConfigRepository = $this->createMock(GroupConfigRepositoryInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->auditLogger = $this->createMock(AuditLoggerInterface::class);
        $this->orderRefundService = $this->createMock(OrderRefundServiceInterface::class);
        $this->transportBuilder = $this->createMock(TransportBuilder::class);
        $this->eventManager = $this->createMock(ManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->dateTime = $this->createMock(DateTime::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->dataObjectFactory = $this->createMock(DataObjectFactory::class);

        // Both approve()/reject() send best-effort emails wrapped in try/catch(\Throwable); an
        // unconfigured storeManager/transportBuilder chain will throw \Error on the first null
        // method call, which those catch blocks swallow and log - so it's safe to leave the
        // rest of the mail-sending chain unconfigured here, matching this test's original intent
        // of not asserting on notification behavior.
        $this->dataObjectFactory->method('create')->willReturn($this->createMock(\Magento\Framework\DataObject::class));
    }

    private function createService(): OrderApprovalService
    {
        return new OrderApprovalService(
            $this->orderApprovalRepository,
            $this->orderApprovalFactory,
            $this->groupConfigRepository,
            $this->orderRepository,
            $this->auditLogger,
            $this->orderRefundService,
            $this->transportBuilder,
            $this->eventManager,
            $this->logger,
            $this->resourceConnection,
            $this->dateTime,
            $this->storeManager,
            $this->dataObjectFactory
        );
    }

    /**
     * @return Order&MockObject
     */
    private function createOrder(int $orderId, int $customerGroupId, int $customerId = 55): Order&MockObject
    {
        $order = $this->createMock(Order::class);
        $order->method('getEntityId')->willReturn($orderId);
        $order->method('getCustomerGroupId')->willReturn($customerGroupId);
        $order->method('getCustomerId')->willReturn($customerId);

        return $order;
    }

    private function createGroupConfig(bool $approvalRequired): GroupConfigInterface&MockObject
    {
        $groupConfig = $this->createMock(GroupConfigInterface::class);
        $groupConfig->method('isApprovalRequired')->willReturn($approvalRequired);

        return $groupConfig;
    }

    private function createApproval(): OrderApprovalInterface&MockObject
    {
        $approval = $this->createMock(OrderApprovalInterface::class);
        $approval->method('setOrderId')->willReturnSelf();
        $approval->method('setCustomerId')->willReturnSelf();
        $approval->method('setStatus')->willReturnSelf();
        $approval->method('setReviewerAdminId')->willReturnSelf();
        $approval->method('setDecisionNotes')->willReturnSelf();
        $approval->method('setDecisionAt')->willReturnSelf();
        $approval->method('setRefundStatus')->willReturnSelf();
        $approval->method('setRefundReference')->willReturnSelf();
        $approval->method('setResubmissionCount')->willReturnSelf();

        return $approval;
    }

    public function testHoldForVerificationDoesNothingWhenApprovalNotRequired(): void
    {
        $order = $this->createOrder(100, 4);
        $this->orderRepository->method('get')->with(100)->willReturn($order);
        $this->groupConfigRepository->method('getByCustomerGroupId')->with(4)
            ->willReturn($this->createGroupConfig(false));

        $this->orderApprovalFactory->expects($this->never())->method('create');
        $this->orderApprovalRepository->expects($this->never())->method('save');

        $this->createService()->holdForVerification(100);
    }

    public function testHoldForVerificationCreatesPendingApprovalWhenRequiredAndNoneExists(): void
    {
        $order = $this->createOrder(100, 4);
        $this->orderRepository->method('get')->with(100)->willReturn($order);
        $this->groupConfigRepository->method('getByCustomerGroupId')->with(4)
            ->willReturn($this->createGroupConfig(true));

        $this->orderApprovalRepository->method('getByOrderId')->with(100)
            ->willThrowException(new NoSuchEntityException(__('not found')));

        $approval = $this->createApproval();
        $this->orderApprovalFactory->method('create')->willReturn($approval);

        $approval->expects($this->once())->method('setStatus')
            ->with(OrderApprovalInterface::STATUS_PENDING_VERIFICATION)
            ->willReturnSelf();

        $this->orderApprovalRepository->expects($this->once())->method('save')->with($approval)
            ->willReturn($approval);

        $this->createService()->holdForVerification(100);
    }

    public function testHoldForVerificationIsIdempotentWhenApprovalAlreadyExists(): void
    {
        $order = $this->createOrder(100, 4);
        $this->orderRepository->method('get')->with(100)->willReturn($order);
        $this->groupConfigRepository->method('getByCustomerGroupId')->with(4)
            ->willReturn($this->createGroupConfig(true));

        $existingApproval = $this->createApproval();
        $existingApproval->method('getStatus')->willReturn(OrderApprovalInterface::STATUS_PENDING_VERIFICATION);
        $this->orderApprovalRepository->method('getByOrderId')->with(100)->willReturn($existingApproval);

        $this->orderApprovalFactory->expects($this->never())->method('create');
        $this->orderApprovalRepository->expects($this->never())->method('save');

        $this->createService()->holdForVerification(100);
    }

    public function testApproveThrowsStateExceptionWhenNotPendingVerification(): void
    {
        $approval = $this->createApproval();
        $approval->method('getStatus')->willReturn(OrderApprovalInterface::STATUS_APPROVED);
        $this->orderApprovalRepository->method('getById')->with(5)->willReturn($approval);

        $this->expectException(StateException::class);

        $this->createService()->approve(5, 9, 'looks fine');
    }

    public function testApproveSetsApprovedStatusReviewerAndDispatchesEvent(): void
    {
        $approval = $this->createApproval();
        $approval->method('getStatus')->willReturn(OrderApprovalInterface::STATUS_PENDING_VERIFICATION);
        $approval->method('getApprovalId')->willReturn(5);
        $approval->method('getOrderId')->willReturn(100);
        $this->orderApprovalRepository->method('getById')->with(5)->willReturn($approval);

        $approval->expects($this->once())->method('setStatus')
            ->with(OrderApprovalInterface::STATUS_APPROVED)
            ->willReturnSelf();
        $approval->expects($this->once())->method('setReviewerAdminId')->with(9)->willReturnSelf();

        $this->orderApprovalRepository->expects($this->once())->method('save')->with($approval)
            ->willReturn($approval);

        $this->eventManager->expects($this->once())->method('dispatch')
            ->with('rgd_cc_order_approved', $this->anything());

        $result = $this->createService()->approve(5, 9, 'looks fine');

        $this->assertSame($approval, $result);
    }

    public function testRejectThrowsBusinessRuleExceptionWhenNotesAreBlank(): void
    {
        $this->expectException(BusinessRuleException::class);

        $this->createService()->reject(5, 9, '   ');
    }

    public function testRejectDoesNotPropagateExceptionWhenRefundFailsAndStillReturnsApproval(): void
    {
        $approval = $this->createApproval();
        $approval->method('getStatus')->willReturn(OrderApprovalInterface::STATUS_PENDING_VERIFICATION);
        $approval->method('getApprovalId')->willReturn(5);
        $approval->method('getOrderId')->willReturn(100);
        $this->orderApprovalRepository->method('getById')->with(5)->willReturn($approval);
        $this->orderApprovalRepository->method('save')->willReturn($approval);

        $approval->expects($this->once())->method('setStatus')
            ->with(OrderApprovalInterface::STATUS_REJECTED)
            ->willReturnSelf();

        $this->auditLogger->expects($this->once())->method('log');

        $this->orderRefundService->expects($this->once())->method('refund')
            ->willThrowException(new \RuntimeException('gateway unreachable'));

        // The resilience behavior under test: refund failure must be swallowed and reflected
        // in refund_status, not rethrown.
        $approval->expects($this->atLeastOnce())->method('setRefundStatus')
            ->with(OrderApprovalInterface::REFUND_STATUS_FAILED)
            ->willReturnSelf();

        $result = $this->createService()->reject(5, 9, 'documents do not match');

        $this->assertSame($approval, $result);
    }

    public function testRetryRefundThrowsStateExceptionWhenRefundStatusNotRetryable(): void
    {
        $approval = $this->createApproval();
        $approval->method('getRefundStatus')->willReturn(OrderApprovalInterface::REFUND_STATUS_COMPLETED);
        $this->orderApprovalRepository->method('getById')->with(5)->willReturn($approval);

        $this->expectException(StateException::class);

        $this->createService()->retryRefund(5);
    }

    public function testRetryRefundSucceedsWhenRefundStatusIsFailed(): void
    {
        $approval = $this->createApproval();
        $approval->method('getRefundStatus')->willReturn(OrderApprovalInterface::REFUND_STATUS_FAILED);
        $approval->method('getOrderId')->willReturn(100);
        $this->orderApprovalRepository->method('getById')->with(5)->willReturn($approval);
        $this->orderApprovalRepository->method('save')->willReturn($approval);

        $this->orderRefundService->expects($this->once())->method('refund');

        $result = $this->createService()->retryRefund(5);

        $this->assertSame($approval, $result);
    }
}
