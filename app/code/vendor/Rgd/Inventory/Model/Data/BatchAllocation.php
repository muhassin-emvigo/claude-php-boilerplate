<?php
declare(strict_types=1);

namespace Rgd\Inventory\Model\Data;

use Rgd\Inventory\Api\Data\BatchAllocationInterface;

/**
 * Batch allocation value object — immutable DTO
 */
class BatchAllocation implements BatchAllocationInterface
{
    private int $batchId;
    private string $batchNumber;
    private ?string $expiryDate;
    private float $qty;

    public function __construct(int $batchId, string $batchNumber, ?string $expiryDate, float $qty)
    {
        $this->batchId = $batchId;
        $this->batchNumber = $batchNumber;
        $this->expiryDate = $expiryDate;
        $this->qty = $qty;
    }

    public function getBatchId(): int
    {
        return $this->batchId;
    }

    public function getBatchNumber(): string
    {
        return $this->batchNumber;
    }

    public function getExpiryDate(): ?string
    {
        return $this->expiryDate;
    }

    public function getQty(): float
    {
        return $this->qty;
    }
}
