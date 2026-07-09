<?php
declare(strict_types=1);

namespace Rgd\Inventory\Model;

use Rgd\Inventory\Api\BatchReturnServiceInterface;
use Rgd\Inventory\Api\Data\BatchAllocationInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;

/**
 * Batch return service — phase 1 stub, not implemented
 */
class BatchReturnService implements BatchReturnServiceInterface
{
    public function restore(
        string $sku,
        float $qty,
        SalesEventInterface $salesEvent,
        ?int $orderItemId,
        string $sourceCode = 'default'
    ): array {
        // Phase 1: stub returns empty array
        return [];
    }
}
