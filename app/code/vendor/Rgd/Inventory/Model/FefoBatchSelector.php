<?php
declare(strict_types=1);

namespace Rgd\Inventory\Model;

use Rgd\Inventory\Api\FefoBatchSelectorInterface;
use Rgd\Inventory\Api\Data\BatchAllocationInterface;
use Rgd\Inventory\Model\Data\BatchAllocation;
use Rgd\Inventory\Model\ResourceModel\Batch\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Zend_Db_Expr;

/**
 * FEFO batch selector — selects batches for deduction without locking
 */
class FefoBatchSelector implements FefoBatchSelectorInterface
{
    public function __construct(
        private CollectionFactory $collectionFactory,
    ) {}

    public function selectForDeduction(string $sku, float $requestedQty, string $sourceCode = 'default'): array
    {
        $collection = $this->collectionFactory->create();

        // Only the columns actually used below are needed — avoid loading full
        // ORM objects (and every other column) for what is effectively a
        // read-only candidate-row scan.
        $collection->addFieldToSelect(['batch_id', 'batch_number', 'expiry_date', 'remaining_qty']);

        // Filter: active, non-expired, with remaining qty > 0
        $collection->addFieldToFilter('sku', $sku)
            ->addFieldToFilter('source_code', $sourceCode)
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('remaining_qty', ['gt' => 0]);

        // A batch expiring today is already treated as expired — only strictly
        // future expiry dates (or no expiry at all) are usable.
        $connection = $collection->getConnection();
        $collection->getSelect()->where(
            $connection->quoteInto('expiry_date IS NULL OR expiry_date > CURDATE()', [])
        );

        // Order by FEFO: earliest expiry first (NULL last), then by batch_id for determinism
        $collection->getSelect()->order([
            new Zend_Db_Expr('expiry_date IS NULL'),
            'expiry_date ASC',
            'batch_id ASC',
        ]);

        // Calculate total available directly from the candidate collection — the
        // "any batches at all" / "all expired" diagnostic queries below are only
        // ever needed to build an error message, so they're deferred into the
        // failure branch instead of running unconditionally on every call.
        $totalAvailable = 0;
        $candidateCount = 0;
        foreach ($collection as $batch) {
            $totalAvailable += $batch->getRemainingQty();
            $candidateCount++;
        }

        if ($candidateCount === 0 || $totalAvailable < $requestedQty) {
            $this->diagnoseAndThrow($sku, $requestedQty, $totalAvailable, $candidateCount, $collection, $sourceCode);
        }

        // Allocate batches
        $allocations = [];
        $remainingQty = $requestedQty;

        foreach ($collection as $batch) {
            if ($remainingQty <= 0) {
                break;
            }

            $batchQty = min($batch->getRemainingQty(), $remainingQty);
            $allocations[] = new BatchAllocation(
                (int) $batch->getBatchId(),
                $batch->getBatchNumber(),
                $batch->getExpiryDate(),
                $batchQty
            );

            $remainingQty -= $batchQty;
        }

        return $allocations;
    }

    /**
     * Diagnose why the candidate scan came up empty/short and throw the appropriate,
     * admin-distinguishable exception. Only ever reached on the failure path — the
     * extra queries here are deliberately not run on the (much more common) success
     * path.
     *
     * @param string $sku
     * @param float $requestedQty
     * @param float $totalAvailable Total remaining_qty across non-expired candidate rows
     * @param int $candidateCount Number of non-expired candidate rows found
     * @param \Rgd\Inventory\Model\ResourceModel\Batch\Collection $candidateCollection
     * @param string $sourceCode
     * @throws LocalizedException Always — one of three distinct messages
     */
    private function diagnoseAndThrow(
        string $sku,
        float $requestedQty,
        float $totalAvailable,
        int $candidateCount,
        $candidateCollection,
        string $sourceCode
    ): void {
        $connection = $candidateCollection->getConnection();

        // COUNT(*) is required here — fetchOne() on a bare from($table) (i.e. SELECT *)
        // returns the first column of the first matching row (batch_id), not a row
        // count, so this must aggregate explicitly rather than rely on fetchOne() alone.
        $totalBatchCount = (int) $connection->fetchOne(
            $connection->select()
                ->from($candidateCollection->getMainTable(), 'COUNT(*)')
                ->where('sku = ?', $sku)
                ->where('source_code = ?', $sourceCode)
                ->where('is_active = 1')
        );

        if ($totalBatchCount === 0) {
            throw new LocalizedException(
                __('No batch inventory configured for SKU "%1".', $sku)
            );
        }

        if ($candidateCount === 0) {
            $expiredCollection = $this->collectionFactory->create();
            $expiredCollection->addFieldToFilter('sku', $sku)
                ->addFieldToFilter('source_code', $sourceCode)
                ->addFieldToFilter('is_active', 1)
                ->addFieldToFilter('remaining_qty', ['gt' => 0])
                ->addFieldToFilter('expiry_date', ['notnull' => true]);

            $expiredQty = 0;
            $expiredCount = 0;
            foreach ($expiredCollection as $expiredBatch) {
                $expiredQty += $expiredBatch->getRemainingQty();
                $expiredCount++;
            }

            if ($expiredCount > 0) {
                throw new LocalizedException(
                    __(
                        'No usable batch stock for SKU "%1": %2 unit(s) exist across %3 batch(es) but all are expired.',
                        $sku,
                        $expiredQty,
                        $totalBatchCount
                    )
                );
            }
        }

        throw new LocalizedException(
            __(
                'Insufficient batch stock for SKU "%1": requested %2, available %3.',
                $sku,
                $requestedQty,
                $totalAvailable
            )
        );
    }
}
