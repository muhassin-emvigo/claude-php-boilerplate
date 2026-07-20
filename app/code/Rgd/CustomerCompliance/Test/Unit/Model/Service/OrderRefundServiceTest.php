<?php

// NOTE: Written in parallel with Model\Service\OrderRefundService. The exact shape/keying of
// the $strategies constructor array (e.g. keyed by strategy code vs. plain priority-ordered
// list) is a best-effort reconstruction. Re-run and adjust against the actual implementation
// during Build-stage integration.

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Test\Unit\Model\Service;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Rgd\CustomerCompliance\Api\Data\RefundResultInterface;
use Rgd\CustomerCompliance\Api\RefundStrategyInterface;
use Rgd\CustomerCompliance\Model\Service\OrderRefundService;

/**
 * @covers \Rgd\CustomerCompliance\Model\Service\OrderRefundService
 */
class OrderRefundServiceTest extends TestCase
{
    private RefundStrategyInterface&MockObject $razorpayStrategy;
    private RefundStrategyInterface&MockObject $offlineStrategy;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->razorpayStrategy = $this->createMock(RefundStrategyInterface::class);
        $this->offlineStrategy = $this->createMock(RefundStrategyInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function createService(array $strategies): OrderRefundService
    {
        return new OrderRefundService($strategies, $this->logger);
    }

    public function testUsesRazorpayStrategyWhenItCanRefundAndDoesNotCallOffline(): void
    {
        $result = $this->createMock(RefundResultInterface::class);
        $result->method('getStatus')->willReturn(RefundResultInterface::STATUS_COMPLETED);

        $this->razorpayStrategy->method('canRefund')->with(100)->willReturn(true);
        $this->razorpayStrategy->expects($this->once())->method('refund')
            ->with(100, 250.0, 'customer requested')
            ->willReturn($result);

        $this->offlineStrategy->expects($this->never())->method('refund');

        $service = $this->createService([
            'razorpay' => $this->razorpayStrategy,
            'offline' => $this->offlineStrategy,
        ]);

        $actual = $service->refund(100, 250.0, 'customer requested');

        $this->assertSame($result, $actual);
    }

    public function testFallsBackToOfflineStrategyWhenRazorpayCannotRefund(): void
    {
        $result = $this->createMock(RefundResultInterface::class);
        $result->method('getStatus')->willReturn(RefundResultInterface::STATUS_MANUAL_FALLBACK);

        $this->razorpayStrategy->method('canRefund')->willReturn(false);
        $this->razorpayStrategy->expects($this->never())->method('refund');

        $this->offlineStrategy->method('canRefund')->willReturn(true);
        $this->offlineStrategy->expects($this->once())->method('refund')
            ->with(100, 250.0, 'gateway refund window expired')
            ->willReturn($result);

        $service = $this->createService([
            'razorpay' => $this->razorpayStrategy,
            'offline' => $this->offlineStrategy,
        ]);

        $actual = $service->refund(100, 250.0, 'gateway refund window expired');

        $this->assertSame($result, $actual);
    }

    public function testThrowsLocalizedExceptionWhenNoStrategiesConfigured(): void
    {
        $this->expectException(LocalizedException::class);

        $this->createService([])->refund(100, 250.0, 'no strategies available');
    }

    public function testThrowsLocalizedExceptionWhenNoConfiguredStrategyCanRefund(): void
    {
        $this->razorpayStrategy->method('canRefund')->willReturn(false);
        $this->offlineStrategy->method('canRefund')->willReturn(false);

        $this->razorpayStrategy->expects($this->never())->method('refund');
        $this->offlineStrategy->expects($this->never())->method('refund');

        $this->expectException(LocalizedException::class);

        $this->createService([
            'razorpay' => $this->razorpayStrategy,
            'offline' => $this->offlineStrategy,
        ])->refund(100, 250.0, 'no capable strategy');
    }
}
