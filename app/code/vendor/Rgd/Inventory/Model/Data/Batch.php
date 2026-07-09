<?php
declare(strict_types=1);

namespace Rgd\Inventory\Model\Data;

use Rgd\Inventory\Api\Data\BatchInterface;
use Magento\Framework\Model\AbstractExtensibleModel;

/**
 * Batch data model
 */
class Batch extends AbstractExtensibleModel implements BatchInterface
{
    protected function _construct(): void
    {
        $this->_init(\Rgd\Inventory\Model\ResourceModel\Batch::class);
    }

    public function getBatchId(): ?int
    {
        return $this->getData(self::BATCH_ID) ? (int) $this->getData(self::BATCH_ID) : null;
    }

    public function setBatchId(?int $batchId): self
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

    public function getReceivedQty(): float
    {
        return (float) $this->getData(self::RECEIVED_QTY);
    }

    public function setReceivedQty(float $qty): self
    {
        return $this->setData(self::RECEIVED_QTY, $qty);
    }

    public function getRemainingQty(): float
    {
        return (float) $this->getData(self::REMAINING_QTY);
    }

    public function setRemainingQty(float $qty): self
    {
        return $this->setData(self::REMAINING_QTY, $qty);
    }

    public function getSourceCode(): string
    {
        return (string) $this->getData(self::SOURCE_CODE);
    }

    public function setSourceCode(string $sourceCode): self
    {
        return $this->setData(self::SOURCE_CODE, $sourceCode);
    }

    public function isActive(): bool
    {
        return (bool) $this->getData(self::IS_ACTIVE);
    }

    public function setIsActive(bool $isActive): self
    {
        return $this->setData(self::IS_ACTIVE, (int) $isActive);
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

    public function getUpdatedAt(): ?string
    {
        $date = $this->getData(self::UPDATED_AT);
        return $date ? (string) $date : null;
    }

    public function setUpdatedAt(string $updatedAt): self
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
