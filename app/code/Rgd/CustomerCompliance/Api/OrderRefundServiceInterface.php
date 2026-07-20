<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Api;

/**
 * Orchestrates refunding an order via the configured refund strategy.
 *
 * @api
 */
interface OrderRefundServiceInterface
{
    /**
     * Refund an order.
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
