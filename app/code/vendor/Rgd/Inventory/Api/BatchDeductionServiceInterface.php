<?php
declare(strict_types=1);

namespace Rgd\Inventory\Api;

use Rgd\Inventory\Api\Data\BatchAllocationInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\CouldNotSaveException;

/**
 * Batch deduction service interface — atomic FEFO batch selection and deduction with locking
 *
 * Public API for external callers needing to perform batch deductions with full transaction
 * management. The deduct() method is the primary public contract.
 *
 * @api
 */
interface BatchDeductionServiceInterface
{
    /**
     * Atomically select FEFO batches and deduct quantity for SKU
     *
     * Owns the transaction boundary: BEGIN -> SELECT...FOR UPDATE -> compute allocation ->
     * UPDATE remaining_qty -> INSERT audit rows -> COMMIT. For standalone callers, this
     * transaction is self-contained. Callers that need atomicity across multiple items
     * and/or with MSI's own proceed() (e.g. SourceDeductionCoordinator) should obtain a
     * direct reference to the concrete BatchDeductionService class and call its
     * deductWithinTransaction() method directly.
     *
     * @param string $sku
     * @param float $qty Positive requested quantity
     * @param SalesEventInterface $salesEvent Disambiguates deduction (shipment/invoice) vs refund
     * @param int|null $orderItemId Resolved by caller; null only for non-order-bound callers
     * @param string $sourceCode
     * @return BatchAllocationInterface[] What was allocated, for logging/telemetry
     * @throws LocalizedException RGD_INV_INSUFFICIENT_STOCK
     * @throws CouldNotSaveException On ledger write failure (transaction rolled back first)
     */
    public function deduct(
        string $sku,
        float $qty,
        SalesEventInterface $salesEvent,
        ?int $orderItemId,
        string $sourceCode = 'default'
    ): array;
}
