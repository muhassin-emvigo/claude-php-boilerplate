<?php
declare(strict_types=1);

namespace Rgd\Inventory\Api\Data;

/**
 * Batch inventory data interface
 *
 * @api
 */
interface BatchInterface
{
    const BATCH_ID = 'batch_id';
    const SKU = 'sku';
    const BATCH_NUMBER = 'batch_number';
    const EXPIRY_DATE = 'expiry_date';
    const RECEIVED_QTY = 'received_qty';
    const REMAINING_QTY = 'remaining_qty';
    const SOURCE_CODE = 'source_code';
    const IS_ACTIVE = 'is_active';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /**
     * Get batch ID
     *
     * @return int|null
     */
    public function getBatchId(): ?int;

    /**
     * Set batch ID
     *
     * @param int|null $batchId
     * @return self
     */
    public function setBatchId(?int $batchId): self;

    /**
     * Get SKU
     *
     * @return string
     */
    public function getSku(): string;

    /**
     * Set SKU
     *
     * @param string $sku
     * @return self
     */
    public function setSku(string $sku): self;

    /**
     * Get batch number
     *
     * @return string
     */
    public function getBatchNumber(): string;

    /**
     * Set batch number
     *
     * @param string $batchNumber
     * @return self
     */
    public function setBatchNumber(string $batchNumber): self;

    /**
     * Get expiry date (ISO date Y-m-d format, null = no expiry tracked)
     *
     * @return string|null
     */
    public function getExpiryDate(): ?string;

    /**
     * Set expiry date
     *
     * @param string|null $expiryDate
     * @return self
     */
    public function setExpiryDate(?string $expiryDate): self;

    /**
     * Get received quantity
     *
     * @return float
     */
    public function getReceivedQty(): float;

    /**
     * Set received quantity
     *
     * @param float $qty
     * @return self
     */
    public function setReceivedQty(float $qty): self;

    /**
     * Get remaining quantity
     *
     * @return float
     */
    public function getRemainingQty(): float;

    /**
     * Set remaining quantity
     *
     * @param float $qty
     * @return self
     */
    public function setRemainingQty(float $qty): self;

    /**
     * Get source code
     *
     * @return string
     */
    public function getSourceCode(): string;

    /**
     * Set source code
     *
     * @param string $sourceCode
     * @return self
     */
    public function setSourceCode(string $sourceCode): self;

    /**
     * Check if batch is active
     *
     * @return bool
     */
    public function isActive(): bool;

    /**
     * Set active status
     *
     * @param bool $isActive
     * @return self
     */
    public function setIsActive(bool $isActive): self;

    /**
     * Get created at timestamp
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * Set created at timestamp
     *
     * @param string $createdAt
     * @return self
     */
    public function setCreatedAt(string $createdAt): self;

    /**
     * Get updated at timestamp
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string;

    /**
     * Set updated at timestamp
     *
     * @param string $updatedAt
     * @return self
     */
    public function setUpdatedAt(string $updatedAt): self;
}
