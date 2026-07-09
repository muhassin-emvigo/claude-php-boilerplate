<?php
declare(strict_types=1);

namespace Rgd\Inventory\Controller\Adminhtml\Batch;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Rgd\Inventory\Api\BatchRepositoryInterface;
use Rgd\Inventory\Api\Data\BatchInterface;
use Rgd\Inventory\Api\Data\BatchTransactionInterface;
use Rgd\Inventory\Model\Data\Batch;
use Rgd\Inventory\Model\Data\BatchFactory;
use Rgd\Inventory\Model\Data\BatchTransaction;
use Rgd\Inventory\Model\Data\BatchTransactionFactory;
use Rgd\Inventory\Model\ResourceModel\BatchTransaction as BatchTransactionResourceModel;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\CouldNotSaveException;
use Zend_Db_Expr;

/**
 * Batch save controller
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) The count here is a direct, deliberate
 *     consequence of writeTransaction()/buildTransaction() taking a single
 *     BatchTransactionInterface object instead of 10 scalar parameters (fixing
 *     ExcessiveParameterList) — reverting to scalars would trade this finding back for
 *     that one. The extra types (BatchInterface, BatchTransactionInterface,
 *     BatchTransactionFactory) are the existing, reused API data contracts, not new
 *     bespoke classes.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Rgd_Inventory::batch_manage';

    public function __construct(
        Context $context,
        private BatchRepositoryInterface $batchRepository,
        private BatchFactory $batchFactory,
        private BatchTransactionFactory $transactionFactory,
        private BatchTransactionResourceModel $transactionResourceModel,
    ) {
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\Controller\Result\Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $batchId = (int)$this->getRequest()->getPostValue('batch_id');

        try {
            $data = $this->getRequest()->getPostValue();

            // Unset system fields that shouldn't be set from user input
            unset($data['batch_id']);

            // Create or load batch, and work out whether a manual adjustment
            // transaction needs to be written after save (edit path only).
            [$batch, $data, $adjustmentQty] = $batchId
                ? $this->prepareExistingBatch($batchId, $data)
                : [$this->batchFactory->create(), $this->prepareNewBatchData($data), 0.0];

            // Populate batch with form data
            $batch->setSku($data['sku'] ?? $batch->getSku());
            $batch->setBatchNumber($data['batch_number'] ?? $batch->getBatchNumber());
            $batch->setSourceCode($data['source_code'] ?? $batch->getSourceCode());
            $batch->setExpiryDate($data['expiry_date'] ?? $batch->getExpiryDate());
            $batch->setReceivedQty((float)($data['received_qty'] ?? $batch->getReceivedQty()));
            $batch->setRemainingQty((float)($data['remaining_qty'] ?? $batch->getRemainingQty()));
            $batch->setIsActive(!empty($data['is_active']));

            // Save batch
            $batch = $this->batchRepository->save($batch);

            // Write transaction rows
            if (!$batchId) {
                // On create, write an 'intake' transaction row
                $this->writeTransaction($this->buildTransaction(
                    $batch,
                    BatchTransaction::MOVEMENT_INTAKE,
                    (float)$batch->getReceivedQty(),
                    'Manual intake'
                ));
            } elseif ($adjustmentQty !== 0.0) {
                // On edit with remaining_qty change, write an 'adjustment' transaction row
                $this->writeTransaction($this->buildTransaction(
                    $batch,
                    BatchTransaction::MOVEMENT_ADJUSTMENT,
                    $adjustmentQty,
                    'Manual adjustment'
                ));
            }

            $this->messageManager->addSuccessMessage((string)__('Batch saved successfully.'));
            return $resultRedirect->setPath('rgd_inventory/batch/index');
        } catch (CouldNotSaveException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $resultRedirect->setPath('rgd_inventory/batch/edit', ['batch_id' => $batchId]);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $resultRedirect->setPath('rgd_inventory/batch/edit', ['batch_id' => $batchId]);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage((string)__('An error occurred while saving the batch.'));
            return $resultRedirect->setPath('rgd_inventory/batch/edit', ['batch_id' => $batchId]);
        }
    }

    /**
     * Load an existing batch and work out whether the edit changed remaining_qty
     * enough to require a manual adjustment transaction.
     *
     * @param int $batchId
     * @param array $data
     * @return array{0: BatchInterface, 1: array, 2: float} [$batch, $data, $adjustmentQty]
     */
    private function prepareExistingBatch(int $batchId, array $data): array
    {
        $batch = $this->batchRepository->getById($batchId);
        // On edit, SKU, batch_number, and source_code are read-only (identity fields)
        unset($data['sku'], $data['batch_number'], $data['source_code']);

        // Handle manual adjustment if remaining_qty changed
        $oldRemainingQty = (float)$batch->getRemainingQty();
        $newRemainingQty = isset($data['remaining_qty']) ? (float)$data['remaining_qty'] : $oldRemainingQty;
        $adjustmentQty = $newRemainingQty !== $oldRemainingQty ? $newRemainingQty - $oldRemainingQty : 0.0;

        return [$batch, $data, $adjustmentQty];
    }

    /**
     * Normalize posted data for a new batch: remaining_qty = received_qty (intake).
     *
     * @param array $data
     * @return array
     */
    private function prepareNewBatchData(array $data): array
    {
        if (isset($data['received_qty'])) {
            $data['remaining_qty'] = $data['received_qty'];
        }

        return $data;
    }

    /**
     * Build an audit ledger transaction record for the given batch.
     */
    private function buildTransaction(
        BatchInterface $batch,
        string $movementType,
        float $qty,
        string $reference
    ): BatchTransactionInterface {
        return $this->transactionFactory->create()
            ->setBatchId((int)$batch->getBatchId())
            ->setSku((string)$batch->getSku())
            ->setBatchNumber((string)$batch->getBatchNumber())
            ->setExpiryDate($batch->getExpiryDate())
            ->setMovementType($movementType)
            ->setQty($qty)
            ->setReference($reference);
    }

    /**
     * Write a transaction row to the audit ledger
     */
    private function writeTransaction(BatchTransactionInterface $transaction): void
    {
        $connection = $this->transactionResourceModel->getConnection();
        $table = $this->transactionResourceModel->getMainTable();

        $connection->insert($table, [
            'batch_id' => $transaction->getBatchId(),
            'sku' => $transaction->getSku(),
            'batch_number' => $transaction->getBatchNumber(),
            'expiry_date' => $transaction->getExpiryDate(),
            'movement_type' => $transaction->getMovementType(),
            'qty' => $transaction->getQty(),
            'sales_event_type' => $transaction->getSalesEventType(),
            'order_id' => $transaction->getOrderId(),
            'order_item_id' => $transaction->getOrderItemId(),
            'reference' => $transaction->getReference(),
            'created_at' => new Zend_Db_Expr('NOW()'),
        ]);
    }
}
