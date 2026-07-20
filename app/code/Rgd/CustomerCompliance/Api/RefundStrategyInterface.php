<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Api;

/**
 * Strategy contract for a single refund mechanism (e.g. online gateway, offline/manual).
 *
 * @api
 */
interface RefundStrategyInterface
{
    /**
     * Determine whether this strategy is able to refund the given order.
     *
     * @param int $orderId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function canRefund(int $orderId): bool;

    /**
     * Refund the given order using this strategy.
     *
     * @param int $orderId
     * @param float $amount
     * @param string $reason
     * @return \Rgd\CustomerCompliance\Api\Data\RefundResultInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function refund(int $orderId, float $amount, string $reason): Data\RefundResultInterface;
}
