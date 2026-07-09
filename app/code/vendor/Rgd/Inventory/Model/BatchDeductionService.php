<?php
declare(strict_types=1);

namespace Rgd\Inventory\Model;

use Rgd\Inventory\Api\BatchDeductionServiceInterface;
use Rgd\Inventory\Api\Data\BatchAllocationInterface;
use Rgd\Inventory\Api\Data\BatchTransactionInterface;
use Rgd\Inventory\Model\Data\BatchAllocation;
use Rgd\Inventory\Model\Data\BatchTransaction;
use Rgd\Inventory\Model\Data\BatchTransactionFactory;
use Rgd\Inventory\Model\ResourceModel\Batch as BatchResourceModel;
use Rgd\Inventory\Model\ResourceModel\BatchTransaction as BatchTransactionResourceModel;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Zend_Db_Expr;

/**
 * Batch deduction service — owns lock/transaction lifecycle
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) The count here reflects the inherent
 *     domain surface of FEFO batch deduction (allocation, locking, audit-ledger writes,
 *     and three distinct Magento exception types across three call sites), not sloppy
 *     design — writeTransaction() already takes a single BatchTransactionInterface
 *     object rather than 10 scalars (see ExcessiveParameterList fix), so the remaining
 *     coupling is the minimum needed to cover locking + allocation + audit-write + the
 *     diagnostic failure paths in one cohesive service.
 */
class BatchDeductionService implements BatchDeductionServiceInterface
{
    public function __construct(
        private BatchTransactionFactory $transactionFactory,
        private BatchTransactionResourceModel $transactionResourceModel,
        private BatchResourceModel $batchResourceModel,
    ) {}


    public function deduct(
        string $sku,
        float $qty,
        SalesEventInterface $salesEvent,
        ?int $orderItemId,
        string $sourceCode = 'default'
    ): array {
        $connection = $this->batchResourceModel->getConnection();

        try {
            $connection->beginTransaction();

            $allocations = $this->deductWithinTransaction(
                $sku,
                $qty,
                $salesEvent,
                $orderItemId,
                $sourceCode,
                $connection,
                null  // orderId is resolved by caller (e.g. SourceDeductionCoordinator)
            );

            $connection->commit();
            return $allocations;
        } catch (LocalizedException $e) {
            $connection->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $connection->rollBack();
            throw new CouldNotSaveException(
                __('Unable to record batch deduction for SKU "%1": %2', $sku, $e->getMessage())
            );
        }
    }

    /**
     * Perform the allocate + update + audit-write work for a single item WITHOUT
     * managing the transaction boundary. Callers (e.g. a coordinator that needs to
     * span multiple items and MSI's own proceed() in one shared transaction) are
     * responsible for beginTransaction()/commit()/rollBack() around this call.
     *
     * deduct() itself uses this internally, wrapped in its own transaction, so
     * standalone callers keep the original atomic-per-call contract.
     *
     * @param string $sku
     * @param float $qty
     * @param SalesEventInterface $salesEvent
     * @param int|null $orderItemId
     * @param string $sourceCode
     * @param AdapterInterface $connection Shared connection/transaction supplied by the caller
     * @param int|null $orderId Order ID for the audit ledger (null for non-order-bound deductions)
     * @return BatchAllocationInterface[]
     * @throws LocalizedException RGD_INV_INSUFFICIENT_STOCK
     * @throws CouldNotSaveException On ledger write failure
     */
    public function deductWithinTransaction(
        string $sku,
        float $qty,
        SalesEventInterface $salesEvent,
        ?int $orderItemId,
        string $sourceCode,
        AdapterInterface $connection,
        ?int $orderId = null
    ): array {
        try {
            // Lock candidate batches for update
            $allocations = $this->allocateWithLock(
                $sku,
                $qty,
                $sourceCode,
                $connection
            );

            // Perform the allocations and update remaining_qty
            foreach ($allocations as $allocation) {
                $this->updateBatchRemaining(
                    $allocation->getBatchId(),
                    $allocation->getQty(),
                    $connection
                );

                // Write audit transaction row
                $transaction = $this->transactionFactory->create()
                    ->setBatchId($allocation->getBatchId())
                    ->setSku($sku)
                    ->setBatchNumber($allocation->getBatchNumber())
                    ->setExpiryDate($allocation->getExpiryDate())
                    ->setMovementType(BatchTransaction::MOVEMENT_DEDUCTION)
                    ->setQty(-$allocation->getQty())
                    ->setSalesEventType($salesEvent->getType())
                    ->setOrderId($orderId)
                    ->setOrderItemId($orderItemId);

                $this->writeTransaction($transaction);
            }

            return $allocations;
        } catch (LocalizedException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Unable to record batch deduction for SKU "%1": %2', $sku, $e->getMessage())
            );
        }
    }

    private function allocateWithLock(string $sku, float $requestedQty, string $sourceCode, AdapterInterface $connection): array
    {
        // Build the SELECT...FOR UPDATE query
        $select = $connection->select()
            ->from($this->batchResourceModel->getMainTable())
            ->where('sku = ?', $sku)
            ->where('source_code = ?', $sourceCode)
            ->where('is_active = 1')
            ->where('remaining_qty > 0')
            // A batch expiring today is already treated as expired — only strictly
            // future expiry dates (or no expiry at all) are usable.
            ->where('expiry_date IS NULL OR expiry_date > CURDATE()')
            ->order([
                new Zend_Db_Expr('expiry_date IS NULL'),
                'expiry_date ASC',
                'batch_id ASC',
            ])
            ->forUpdate();

        $rows = $connection->fetchAll($select);

        // Calculate total available directly from the locked candidate rows — no
        // need for a separate query on the success path. The "all batches"/
        // "expired batches" diagnostic queries below are only ever needed to build
        // an error message, so they're deferred into the failure branches instead
        // of running unconditionally on every call.
        $totalAvailable = 0;
        $candidateCount = count($rows);
        foreach ($rows as $row) {
            $totalAvailable += (float) $row['remaining_qty'];
        }

        if ($candidateCount === 0 || $totalAvailable < $requestedQty) {
            $this->diagnoseAndThrow($sku, $requestedQty, $totalAvailable, $candidateCount, $connection, $sourceCode);
        }

        // Build allocations from locked rows
        $allocations = [];
        $remainingQty = $requestedQty;

        foreach ($rows as $row) {
            if ($remainingQty <= 0) {
                break;
            }

            $batchQty = min((float) $row['remaining_qty'], $remainingQty);
            $allocations[] = new BatchAllocation(
                (int) $row['batch_id'],
                (string) $row['batch_number'],
                $row['expiry_date'] ? (string) $row['expiry_date'] : null,
                $batchQty
            );

            $remainingQty -= $batchQty;
        }

        return $allocations;
    }

    /**
     * Diagnose why the locked candidate scan came up empty/short and throw the
     * appropriate, admin-distinguishable exception. Only ever reached on the
     * failure path — these extra queries are deliberately not run on the success
     * path.
     *
     * @param string $sku
     * @param float $requestedQty
     * @param float $totalAvailable Total remaining_qty across locked, non-expired candidate rows
     * @param int $candidateCount Number of locked, non-expired candidate rows found
     * @param AdapterInterface $connection
     * @param string $sourceCode
     * @throws LocalizedException Always — one of three distinct messages
     */
    private function diagnoseAndThrow(
        string $sku,
        float $requestedQty,
        float $totalAvailable,
        int $candidateCount,
        AdapterInterface $connection,
        string $sourceCode
    ): void {
        $table = $this->batchResourceModel->getMainTable();

        $allActiveRows = $connection->fetchAll(
            $connection->select()
                ->from($table)
                ->where('sku = ?', $sku)
                ->where('source_code = ?', $sourceCode)
                ->where('is_active = 1')
        );

        if (empty($allActiveRows)) {
            throw new LocalizedException(
                __('No batch inventory configured for SKU "%1".', $sku)
            );
        }

        if ($candidateCount === 0) {
            $expiredRows = $connection->fetchAll(
                $connection->select()
                    ->from($table)
                    ->where('sku = ?', $sku)
                    ->where('source_code = ?', $sourceCode)
                    ->where('is_active = 1')
                    ->where('remaining_qty > 0')
                    ->where('expiry_date IS NOT NULL AND expiry_date <= CURDATE()')
            );

            if (!empty($expiredRows)) {
                $expiredQty = 0;
                foreach ($expiredRows as $row) {
                    $expiredQty += (float) $row['remaining_qty'];
                }

                throw new LocalizedException(
                    __(
                        'No usable batch stock for SKU "%1": %2 unit(s) exist across %3 batch(es) but all are expired.',
                        $sku,
                        $expiredQty,
                        count($allActiveRows)
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

    private function updateBatchRemaining(int $batchId, float $deductQty, AdapterInterface $connection): void
    {
        $table = $this->batchResourceModel->getMainTable();

        // Defense-in-depth: even though allocateWithLock() already took a
        // SELECT ... FOR UPDATE lock on this row within the same transaction, guard
        // the UPDATE itself against ever driving remaining_qty negative (e.g. if the
        // locking assumption is ever violated) instead of letting the unsigned column
        // fail with an opaque DB error mid-checkout.
        $affectedRows = $connection->update(
            $table,
            ['remaining_qty' => new Zend_Db_Expr('remaining_qty - ' . (float) $deductQty)],
            [
                'batch_id = ?' => $batchId,
                'remaining_qty >= ?' => $deductQty,
            ]
        );

        if ($affectedRows !== 1) {
            throw new LocalizedException(
                __(
                    'Unable to deduct %1 unit(s) from batch ID "%2": remaining quantity changed unexpectedly.',
                    $deductQty,
                    $batchId
                )
            );
        }
    }

    private function writeTransaction(BatchTransactionInterface $transaction): void
    {
        try {
            $this->transactionResourceModel->save($transaction);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not write batch transaction record: %1', $e->getMessage())
            );
        }
    }
}
