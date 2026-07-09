<?php
declare(strict_types=1);

namespace Rgd\Inventory\Model;

use Rgd\Inventory\Api\BatchRepositoryInterface;
use Rgd\Inventory\Api\Data\BatchInterface;
use Rgd\Inventory\Api\Data\BatchSearchResultsInterface;
use Rgd\Inventory\Api\Data\BatchSearchResultsInterfaceFactory;
use Rgd\Inventory\Model\Data\Batch;
use Rgd\Inventory\Model\Data\BatchFactory;
use Rgd\Inventory\Model\ResourceModel\Batch as BatchResourceModel;
use Rgd\Inventory\Model\ResourceModel\Batch\Collection;
use Rgd\Inventory\Model\ResourceModel\Batch\CollectionFactory;
use Rgd\Inventory\Model\ResourceModel\BatchTransaction as BatchTransactionResourceModel;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface;

/**
 * Batch repository
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Standard Magento repository shape:
 *     factory + search-results-factory + collection-factory + resource model(s) +
 *     extension-attribute join processor is the conventional dependency set for a
 *     service-contract repository (see e.g. core ProductRepository) — splitting this
 *     further would fragment a single cohesive responsibility rather than reduce it.
 */
class BatchRepository implements BatchRepositoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.LongVariable) $extensionAttributesJoinProcessor matches
     *     Magento core's own established property name for JoinProcessorInterface
     *     (see e.g. \Magento\Catalog\Model\ProductRepository) — renaming it would break
     *     with a well-recognized, widely-used Magento convention for no real benefit.
     */
    public function __construct(
        private BatchFactory $batchFactory,
        private BatchSearchResultsInterfaceFactory $searchResultsFactory,
        private CollectionFactory $collectionFactory,
        private BatchResourceModel $batchResourceModel,
        private BatchTransactionResourceModel $transactionResourceModel,
        private JoinProcessorInterface $extensionAttributesJoinProcessor,
    ) {}

    public function save(BatchInterface $batch): BatchInterface
    {
        $this->validateForSave($batch);

        try {
            $this->batchResourceModel->save($batch);
        } catch (\Magento\Framework\DB\Adapter\DuplicateException $e) {
            // MySQL/MariaDB throws DuplicateException on unique key violation
            throw new CouldNotSaveException(
                __(
                    'A batch with number "%1" already exists for SKU "%2" and source "%3".',
                    $batch->getBatchNumber(),
                    $batch->getSku(),
                    $batch->getSourceCode()
                )
            );
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save batch: %1', $e->getMessage()));
        }

        return $batch;
    }

    /**
     * Validate business rules on a batch before it is persisted.
     *
     * @param BatchInterface $batch
     * @throws CouldNotSaveException
     */
    private function validateForSave(BatchInterface $batch): void
    {
        if ($batch->getReceivedQty() < 0) {
            throw new CouldNotSaveException(
                __('Cannot save batch: received quantity cannot be negative.')
            );
        }

        if ($batch->getRemainingQty() < 0) {
            throw new CouldNotSaveException(
                __('Cannot save batch: remaining quantity cannot be negative.')
            );
        }

        if ($batch->getRemainingQty() > $batch->getReceivedQty()) {
            throw new CouldNotSaveException(
                __('Cannot save batch: remaining quantity cannot exceed received quantity.')
            );
        }

        $this->validateExpiryDateFormat($batch->getExpiryDate());
    }

    /**
     * Validate that, if set, expiry_date matches Y-m-d format exactly.
     *
     * @param string|null $expiryDate
     * @throws CouldNotSaveException
     */
    private function validateExpiryDateFormat(?string $expiryDate): void
    {
        if ($expiryDate === null) {
            return;
        }

        $parsed = \DateTime::createFromFormat('Y-m-d', $expiryDate);
        $formatErrors = \DateTime::getLastErrors();
        $hasFormatErrors = $formatErrors !== false
            && ($formatErrors['warning_count'] > 0 || $formatErrors['error_count'] > 0);

        if (!$parsed || $hasFormatErrors || $parsed->format('Y-m-d') !== $expiryDate) {
            throw new CouldNotSaveException(
                __('Cannot save batch: expiry date "%1" must be in Y-m-d format.', $expiryDate)
            );
        }
    }

    public function getById(int $batchId): BatchInterface
    {
        $batch = $this->batchFactory->create();
        $this->batchResourceModel->load($batch, $batchId);

        if (!$batch->getId()) {
            throw new NoSuchEntityException(
                __('Batch with ID "%1" does not exist.', $batchId)
            );
        }

        return $batch;
    }

    public function getBySkuAndBatchNumber(string $sku, string $batchNumber, string $sourceCode = 'default'): BatchInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('sku', $sku)
            ->addFieldToFilter('batch_number', $batchNumber)
            ->addFieldToFilter('source_code', $sourceCode);

        // Collection::getFirstItem() is typed \Magento\Framework\DataObject by Magento
        // core (Data\Collection::getFirstItem()), but this collection is _init()'d with
        // Batch::class as its item object, so it always returns a Batch at runtime.
        // Batch (not just BatchInterface) is needed here for getId(), which is an
        // AbstractModel method rather than part of the BatchInterface API contract.
        /** @var Batch $batch */
        $batch = $collection->getFirstItem();

        if (!$batch->getId()) {
            throw new NoSuchEntityException(
                __('Batch with number "%1" for SKU "%2" in source "%3" does not exist.', $batchNumber, $sku, $sourceCode)
            );
        }

        return $batch;
    }

    public function getList(SearchCriteriaInterface $searchCriteria): BatchSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();

        $this->extensionAttributesJoinProcessor->process($collection, BatchInterface::class);

        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            foreach ($filterGroup->getFilters() as $filter) {
                $condition = $filter->getConditionType() ?? 'eq';
                $collection->addFieldToFilter($filter->getField(), [$condition => $filter->getValue()]);
            }
        }

        $sortOrders = $searchCriteria->getSortOrders();
        if ($sortOrders) {
            foreach ($sortOrders as $sortOrder) {
                $collection->addOrder(
                    $sortOrder->getField(),
                    $sortOrder->getDirection() === 'ASC' ? 'ASC' : 'DESC'
                );
            }
        }

        $collection->setCurPage($searchCriteria->getCurrentPage());
        $collection->setPageSize($searchCriteria->getPageSize());

        // Collection::getItems() is typed \Magento\Framework\DataObject[] by Magento core
        // (Data\Collection::getItems()), but this collection is _init()'d with Batch::class
        // as its item object, so it always returns Batch[] (which satisfies BatchInterface[]).
        /** @var BatchInterface[] $items */
        $items = $collection->getItems();

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($items);
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    public function delete(BatchInterface $batch): bool
    {
        return $this->deleteById($batch->getBatchId() ?? 0);
    }

    public function deleteById(int $batchId): bool
    {
        $batch = $this->getById($batchId);

        // Check if batch has transaction history
        $transactionCollection = $this->transactionResourceModel
            ->getConnection()
            ->select()
            ->from($this->transactionResourceModel->getMainTable())
            ->where('batch_id = ?', $batchId)
            ->limit(1);

        if ($this->transactionResourceModel->getConnection()->fetchOne($transactionCollection)) {
            throw new CouldNotDeleteException(
                __('Cannot delete batch "%1": it has recorded inventory transactions. Deactivate it instead.', $batch->getBatchNumber())
            );
        }

        try {
            $this->batchResourceModel->delete($batch);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete batch: %1', $e->getMessage()));
        }

        return true;
    }
}
