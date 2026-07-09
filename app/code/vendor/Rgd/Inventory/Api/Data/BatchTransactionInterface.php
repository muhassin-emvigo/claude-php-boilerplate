<?php
declare(strict_types=1);

namespace Rgd\Inventory\Api\Data;

/**
 * Batch transaction (audit log) data interface
 *
 * @api
 */
interface BatchTransactionInterface
{
    const TRANSACTION_ID = 'transaction_id';
    const BATCH_ID = 'batch_id';
    const SKU = 'sku';
    const BATCH_NUMBER = 'batch_number';
    const EXPIRY_DATE = 'expiry_date';
    const MOVEMENT_TYPE = 'movement_type';
    const QTY = 'qty';
    const SALES_EVENT_TYPE = 'sales_event_type';
    const ORDER_ID = 'order_id';
    const ORDER_ITEM_ID = 'order_item_id';
    const REFERENCE = 'reference';
    const CREATED_AT = 'created_at';

    const MOVEMENT_DEDUCTION = 'deduction';
    const MOVEMENT_RETURN = 'return';
    const MOVEMENT_ADJUSTMENT = 'adjustment';
    const MOVEMENT_INTAKE = 'intake';

    /**
     * Get transaction ID
     *
     * @return int|null
     */
    public function getTransactionId(): ?int;

    /**
     * Set transaction ID
     *
     * @param int|null $id
     * @return self
     */
    public function setTransactionId(?int $id): self;

    /**
     * Get batch ID
     *
     * @return int
     */
    public function getBatchId(): int;

    /**
     * Set batch ID
     *
     * @param int $batchId
     * @return self
     */
    public function setBatchId(int $batchId): self;

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
     * Get expiry date (denormalized snapshot)
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
     * Get movement type (deduction|return|adjustment|intake)
     *
     * @return string
     */
    public function getMovementType(): string;

    /**
     * Set movement type
     *
     * @param string $movementType
     * @return self
     */
    public function setMovementType(string $movementType): self;

    /**
     * Get quantity (signed: negative for deduction/adjustment-down, positive for return/intake/adjustment-up)
     *
     * @return float
     */
    public function getQty(): float;

    /**
     * Set quantity
     *
     * @param float $qty
     * @return self
     */
    public function setQty(float $qty): self;

    /**
     * Get sales event type (raw SalesEventInterface::getType() value, null for intake/adjustment)
     *
     * @return string|null
     */
    public function getSalesEventType(): ?string;

    /**
     * Set sales event type
     *
     * @param string|null $salesEventType
     * @return self
     */
    public function setSalesEventType(?string $salesEventType): self;

    /**
     * Get order ID
     *
     * @return int|null
     */
    public function getOrderId(): ?int;

    /**
     * Set order ID
     *
     * @param int|null $orderId
     * @return self
     */
    public function setOrderId(?int $orderId): self;

    /**
     * Get order item ID
     *
     * @return int|null
     */
    public function getOrderItemId(): ?int;

    /**
     * Set order item ID
     *
     * @param int|null $orderItemId
     * @return self
     */
    public function setOrderItemId(?int $orderItemId): self;

    /**
     * Get reference (free-text: creditmemo increment id, admin adjustment note, etc.)
     *
     * @return string|null
     */
    public function getReference(): ?string;

    /**
     * Set reference
     *
     * @param string|null $reference
     * @return self
     */
    public function setReference(?string $reference): self;

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
}
