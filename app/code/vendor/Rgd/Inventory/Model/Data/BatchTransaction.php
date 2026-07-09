<?php
declare(strict_types=1);

namespace Rgd\Inventory\Model\Data;

use Rgd\Inventory\Api\Data\BatchTransactionInterface;
use Magento\Framework\Model\AbstractExtensibleModel;

/**
 * Batch transaction (audit log) model
 */
class BatchTransaction extends AbstractExtensibleModel implements BatchTransactionInterface
{
    protected function _construct(): void
    {
        $this->_init(\Rgd\Inventory\Model\ResourceModel\BatchTransaction::class);
    }

    public function getTransactionId(): ?int
    {
        return $this->getData(self::TRANSACTION_ID) ? (int) $this->getData(self::TRANSACTION_ID) : null;
    }

    public function setTransactionId(?int $id): self
    {
        return $this->setData(self::TRANSACTION_ID, $id);
    }

    public function getBatchId(): int
    {
        return (int) $this->getData(self::BATCH_ID);
    }

    public function setBatchId(int $batchId): self
    {
        return $this->setData(self::BATCH_ID, $batchId);
    }

    public function getSku(): string
    {
        return (string) $this->getData(self::SKU);
    }

    public function setSku(string $sku): self
    {
        return $this->setData(self::SKU, $sku);
    }

    public function getBatchNumber(): string
    {
        return (string) $this->getData(self::BATCH_NUMBER);
    }

    public function setBatchNumber(string $batchNumber): self
    {
        return $this->setData(self::BATCH_NUMBER, $batchNumber);
    }

    public function getExpiryDate(): ?string
    {
        $date = $this->getData(self::EXPIRY_DATE);
        return $date ? (string) $date : null;
    }

    public function setExpiryDate(?string $expiryDate): self
    {
        return $this->setData(self::EXPIRY_DATE, $expiryDate);
    }

    public function getMovementType(): string
    {
        return (string) $this->getData(self::MOVEMENT_TYPE);
    }

    public function setMovementType(string $movementType): self
    {
        return $this->setData(self::MOVEMENT_TYPE, $movementType);
    }

    public function getQty(): float
    {
        return (float) $this->getData(self::QTY);
    }

    public function setQty(float $qty): self
    {
        return $this->setData(self::QTY, $qty);
    }

    public function getSalesEventType(): ?string
    {
        $type = $this->getData(self::SALES_EVENT_TYPE);
        return $type ? (string) $type : null;
    }

    public function setSalesEventType(?string $salesEventType): self
    {
        return $this->setData(self::SALES_EVENT_TYPE, $salesEventType);
    }

    public function getOrderId(): ?int
    {
        return $this->getData(self::ORDER_ID) ? (int) $this->getData(self::ORDER_ID) : null;
    }

    public function setOrderId(?int $orderId): self
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    public function getOrderItemId(): ?int
    {
        return $this->getData(self::ORDER_ITEM_ID) ? (int) $this->getData(self::ORDER_ITEM_ID) : null;
    }

    public function setOrderItemId(?int $orderItemId): self
    {
        return $this->setData(self::ORDER_ITEM_ID, $orderItemId);
    }

    public function getReference(): ?string
    {
        $ref = $this->getData(self::REFERENCE);
        return $ref ? (string) $ref : null;
    }

    public function setReference(?string $reference): self
    {
        return $this->setData(self::REFERENCE, $reference);
    }

    public function getCreatedAt(): ?string
    {
        $date = $this->getData(self::CREATED_AT);
        return $date ? (string) $date : null;
    }

    public function setCreatedAt(string $createdAt): self
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }
}
