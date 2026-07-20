<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Test\Unit\Model\Refund;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Service\CreditmemoService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Rgd\CustomerCompliance\Api\Data\RefundResultInterface;
use Rgd\CustomerCompliance\Model\Data\RefundResultFactory;
use Rgd\CustomerCompliance\Model\Refund\OfflineFallbackRefundStrategy;

/**
 * @covers \Rgd\CustomerCompliance\Model\Refund\OfflineFallbackRefundStrategy
 */
class OfflineFallbackRefundStrategyTest extends TestCase
{
    private OrderRepositoryInterface&MockObject $orderRepository;
    private CreditmemoFactory&MockObject $creditmemoFactory;
    private CreditmemoService&MockObject $creditmemoService;
    private RefundResultFactory&MockObject $refundResultFactory;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->creditmemoFactory = $this->createMock(CreditmemoFactory::class);
        $this->creditmemoService = $this->createMock(CreditmemoService::class);
        $this->refundResultFactory = $this->createMock(RefundResultFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function createStrategy(): OfflineFallbackRefundStrategy
    {
        return new OfflineFallbackRefundStrategy(
            $this->orderRepository,
            $this->creditmemoFactory,
            $this->creditmemoService,
            $this->refundResultFactory,
            $this->logger
        );
    }

    /**
     * @return RefundResultInterface&MockObject
     */
    private function createRefundResultDouble(): RefundResultInterface&MockObject
    {
        $result = $this->createMock(RefundResultInterface::class);
        $result->method('setStatus')->willReturnSelf();
        $result->method('setReference')->willReturnSelf();
        $result->method('setMessage')->willReturnSelf();

        return $result;
    }

    public function testCanRefundReturnsTrueUnconditionally(): void
    {
        $strategy = $this->createStrategy();

        $this->assertTrue($strategy->canRefund(1));
    }

    public function testCanRefundReturnsTrueRegardlessOfOrderId(): void
    {
        $strategy = $this->createStrategy();

        $this->assertTrue($strategy->canRefund(0));
        $this->assertTrue($strategy->canRefund(999999));
    }

    public function testRefundCreatesOfflineCreditMemoAndReportsManualFallback(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $this->orderRepository->method('get')->with(100)->willReturn($order);

        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo->method('getIncrementId')->willReturn('100000123');
        $this->creditmemoFactory->expects($this->once())->method('createByOrder')
            ->with($order, ['comment_text' => 'customer requested'])
            ->willReturn($creditmemo);

        // $isOnline must be false: this strategy never talks to a gateway, it only records the
        // credit memo in Magento and expects a human to complete the actual money movement.
        $this->creditmemoService->expects($this->once())->method('refund')->with($creditmemo, false);

        $result = $this->createRefundResultDouble();
        $this->refundResultFactory->method('create')->willReturn($result);

        $result->expects($this->once())->method('setStatus')
            ->with(RefundResultInterface::STATUS_MANUAL_FALLBACK)->willReturnSelf();
        $result->expects($this->once())->method('setReference')->with('100000123')->willReturnSelf();

        $this->logger->expects($this->never())->method('error');

        $actual = $this->createStrategy()->refund(100, 250.0, 'customer requested');

        $this->assertSame($result, $actual);
    }

    public function testRefundCapturesFailureAsStatusFailedRatherThanThrowing(): void
    {
        $this->orderRepository->method('get')->with(100)
            ->willThrowException(new \RuntimeException('order lookup failed'));

        $this->creditmemoService->expects($this->never())->method('refund');

        $result = $this->createRefundResultDouble();
        $this->refundResultFactory->method('create')->willReturn($result);

        $result->expects($this->once())->method('setStatus')
            ->with(RefundResultInterface::STATUS_FAILED)->willReturnSelf();
        $result->expects($this->once())->method('setReference')->with(null)->willReturnSelf();

        $this->logger->expects($this->once())->method('error');

        // The resilience behavior under test: a failure while building/recording the credit memo
        // must be captured on the result object, never rethrown to the caller.
        $actual = $this->createStrategy()->refund(100, 250.0, 'customer requested');

        $this->assertSame($result, $actual);
    }
}
