<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model\Service;

use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Rgd\CustomerCompliance\Api\Data;
use Rgd\CustomerCompliance\Api\OrderRefundServiceInterface;
use Rgd\CustomerCompliance\Api\RefundStrategyInterface;

/**
 * Orchestrates refunding an order via the configured refund strategy.
 *
 * The preference order is explicit (razorpay first, offline last) rather than relying on the
 * iteration order of the injected `$strategies` map, since that map is assembled by a
 * `TMap`/virtualType in di.xml and its iteration order should not be treated as a contract.
 */
class OrderRefundService implements OrderRefundServiceInterface
{
    /**
     * @param RefundStrategyInterface[] $strategies Keyed strategy pool, e.g. ['razorpay' => ..., 'offline' => ...].
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly array $strategies,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function refund(int $orderId, float $amount, string $reason): Data\RefundResultInterface
    {
        $strategy = $this->resolveStrategy($orderId, 'razorpay') ?? $this->resolveStrategy($orderId, 'offline');

        if ($strategy === null) {
            // Should not happen in practice, since the offline strategy always reports
            // itself as able to refund - this is a defensive guard against misconfiguration.
            $this->logger->error('No refund strategy was able to handle order "' . $orderId . '".');

            throw new LocalizedException(__('No refund strategy available.'));
        }

        return $strategy->refund($orderId, $amount, $reason);
    }

    /**
     * Resolve a named strategy from the pool.
     *
     * Only returns a strategy if it is present in the pool and reports itself as able to
     * refund the given order.
     *
     * @param int $orderId
     * @param string $strategyKey
     * @return RefundStrategyInterface|null
     */
    private function resolveStrategy(int $orderId, string $strategyKey): ?RefundStrategyInterface
    {
        $strategy = $this->strategies[$strategyKey] ?? null;

        if (!$strategy instanceof RefundStrategyInterface) {
            return null;
        }

        return $strategy->canRefund($orderId) ? $strategy : null;
    }
}
