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
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryApi\Api\SourceRepositoryInterface;
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

    /**
     * Matches the max_text_length rule declared for batch_number in
     * rgd_inventory_batch_form.xml — client-side UI validation only, so it
     * must be re-enforced here since a direct POST bypasses it entirely.
     */
    private const BATCH_NUMBER_MAX_LENGTH = 64;

    public function __construct(
        Context $context,
        private BatchRepositoryInterface $batchRepository,
        private BatchFactory $batchFactory,
        private BatchTransactionFactory $transactionFactory,
        private BatchTransactionResourceModel $transactionResourceModel,
        private SourceRepositoryInterface $sourceRepository,
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
            $batch->setExpiryDate($this->normalizeExpiryDate($data['expiry_date'] ?? $batch->getExpiryDate()));
            $batch->setReceivedQty((float)($data['received_qty'] ?? $batch->getReceivedQty()));
            $batch->setRemainingQty((float)($data['remaining_qty'] ?? $batch->getRemainingQty()));
            $batch->setIsActive(!empty($data['is_active']));

            // Server-side validation — the form XML's <validation> rules are
            // client-side only and can be bypassed by a direct POST. Re-check
            // the same constraints here before anything is persisted.
            $this->validateBatch($batch);

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
     * Server-side validation for batch save. Mirrors the admin form's
     * client-side <validation> rules (rgd_inventory_batch_form.xml) — those
     * are enforced by the browser only, so a direct POST bypassing the UI
     * must be re-checked here before the batch is persisted.
     *
     * @param BatchInterface $batch
     * @throws InputException On the first validation failure encountered
     */
    private function validateBatch(BatchInterface $batch): void
    {
        $batchNumber = trim((string)$batch->getBatchNumber());
        if ($batchNumber === '') {
            throw new InputException(__('Batch Number is required.'));
        }

        if (mb_strlen($batchNumber) > self::BATCH_NUMBER_MAX_LENGTH) {
            throw new InputException(
                __('Batch Number cannot exceed %1 characters.', self::BATCH_NUMBER_MAX_LENGTH)
            );
        }

        $this->validateSourceCode(trim((string)$batch->getSourceCode()));

        if (!$this->isValidExpiryDate($batch->getExpiryDate())) {
            throw new InputException(__('Expiry Date is required and must be a valid date.'));
        }

        $receivedQty = (float)$batch->getReceivedQty();
        $remainingQty = (float)$batch->getRemainingQty();

        if ($receivedQty < 0) {
            throw new InputException(__('Received Qty cannot be negative.'));
        }

        if ($remainingQty < 0) {
            throw new InputException(__('Remaining Qty cannot be negative.'));
        }

        if ($remainingQty > $receivedQty) {
            throw new InputException(__('Remaining Qty cannot exceed Received Qty.'));
        }
    }

    /**
     * Validate that source_code is present and refers to a real, existing MSI
     * source. The admin form's <select> currently only ever offers a single
     * 'default' option, but the FEFO deduction path (SourceDeductionCoordinator)
     * reads the real source code straight off MSI's own SourceDeductionRequest,
     * not a hardcoded value — so a direct POST with a bogus source_code would
     * silently persist a batch that live deduction can never match. Validating
     * against SourceRepositoryInterface (rather than just checking against the
     * form's single hardcoded option) keeps this correct if/when the form ever
     * grows to offer more than one source.
     *
     * @param string $sourceCode
     * @throws InputException
     */
    private function validateSourceCode(string $sourceCode): void
    {
        if ($sourceCode === '') {
            throw new InputException(__('Source is required.'));
        }

        try {
            $this->sourceRepository->get($sourceCode);
        } catch (NoSuchEntityException $e) {
            throw new InputException(__('Source "%1" does not exist.', $sourceCode));
        }
    }

    /**
     * Normalize a posted expiry_date to canonical Y-m-d before validation/save.
     *
     * The admin form's date picker (Magento_Ui/js/form/element/date) is
     * configured to submit ISO format, but browser/locale-dependent date
     * pickers are notoriously inconsistent about the exact string they send
     * (a trailing midnight time component, non-zero-padded month/day, etc.).
     * Rather than reject any value that isn't a byte-exact 'Y-m-d' string,
     * parse it leniently with DateTime's standard parser and re-emit it in
     * canonical form — this is the actual trust boundary for user input, so
     * normalizing here (once) keeps both isValidExpiryDate() below and
     * BatchRepository's own strict format check working against a clean,
     * predictable value regardless of which format variant was submitted.
     * A value DateTime cannot parse at all is passed through unchanged so
     * isValidExpiryDate() can reject it with a clear error message.
     *
     * @param string|null $expiryDate
     * @return string|null
     */
    private function normalizeExpiryDate(?string $expiryDate): ?string
    {
        $expiryDate = trim((string)$expiryDate);
        if ($expiryDate === '') {
            return null;
        }

        try {
            return (new \DateTime($expiryDate))->format('Y-m-d');
        } catch (\Exception $e) {
            return $expiryDate;
        }
    }

    /**
     * Validate that expiry_date is present and matches Y-m-d format exactly.
     * Stricter than BatchRepository's own (nullable-permitting) format check
     * by design — the admin form declares expiry_date as required-entry, so
     * the controller boundary enforces non-empty in addition to format.
     * Expects an already-normalized value (see normalizeExpiryDate()).
     *
     * @param string|null $expiryDate
     * @return bool
     */
    private function isValidExpiryDate(?string $expiryDate): bool
    {
        $expiryDate = trim((string)$expiryDate);
        if ($expiryDate === '') {
            return false;
        }

        $parsed = \DateTime::createFromFormat('Y-m-d', $expiryDate);
        $formatErrors = \DateTime::getLastErrors();
        $hasFormatErrors = $formatErrors !== false
            && ($formatErrors['warning_count'] > 0 || $formatErrors['error_count'] > 0);

        return $parsed !== false && !$hasFormatErrors && $parsed->format('Y-m-d') === $expiryDate;
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
