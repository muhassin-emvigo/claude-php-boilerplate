<?php
declare(strict_types=1);

namespace Rgd\Inventory\Api;

use Rgd\Inventory\Api\Data\BatchAllocationInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * FEFO batch selector interface — read-only batch selection without locking
 *
 * @api
 */
interface FefoBatchSelectorInterface
{
    /**
     * Select batches to cover requested quantity for SKU under FEFO ordering
     * (earliest expiry_date first; NULL-expiry batches ordered last)
     *
     * Restricted to active, non-expired (expiry_date > CURDATE() or NULL —
     * a batch expiring today is already treated as expired), remaining_qty > 0
     * batches. Does not lock or mutate state.
     *
     * @param string $sku
     * @param float $requestedQty
     * @param string $sourceCode
     * @return BatchAllocationInterface[] Ordered list; sum(qty) === $requestedQty on success
     * @throws LocalizedException RGD_INV_INSUFFICIENT_STOCK if insufficient stock
     */
    public function selectForDeduction(string $sku, float $requestedQty, string $sourceCode = 'default'): array;
}
