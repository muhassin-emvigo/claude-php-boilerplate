<?php
declare(strict_types=1);

namespace Rgd\Inventory\Api\Data;

/**
 * Batch allocation DTO — represents quantity allocated from a specific batch
 * Immutable value object returned by FEFO selector and deduction service
 *
 * @api
 */
interface BatchAllocationInterface
{
    /**
     * Get batch ID
     *
     * @return int
     */
    public function getBatchId(): int;

    /**
     * Get batch number
     *
     * @return string
     */
    public function getBatchNumber(): string;

    /**
     * Get expiry date
     *
     * @return string|null
     */
    public function getExpiryDate(): ?string;

    /**
     * Get allocated quantity
     *
     * @return float
     */
    public function getQty(): float;
}
