<?php
declare(strict_types=1);

namespace Rgd\Inventory\Api;

use Rgd\Inventory\Api\Data\BatchAllocationInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Batch return service interface — phase 2 contract, not implemented in phase 1
 *
 * Restore quantity back to the batch(es) it was originally deducted from.
 *
 * @api
 */
interface BatchReturnServiceInterface
{
    /**
     * Restore quantity for SKU back to original batch(es)
     *
     * Phase 1: stub implementation (returns [] or throws NotImplementedException).
     *
     * @param string $sku
     * @param float $qty
     * @param SalesEventInterface $salesEvent Expected type: EVENT_CREDITMEMO_CREATED
     * @param int|null $orderItemId
     * @param string $sourceCode
     * @return BatchAllocationInterface[] What was restored to which batch(es); phase 1 returns []
     * @throws LocalizedException
     */
    public function restore(
        string $sku,
        float $qty,
        SalesEventInterface $salesEvent,
        ?int $orderItemId,
        string $sourceCode = 'default'
    ): array;
}
