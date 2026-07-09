<?php
declare(strict_types=1);

namespace Rgd\Inventory\Api;

use Rgd\Inventory\Api\Data\BatchInterface;
use Rgd\Inventory\Api\Data\BatchSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Batch repository interface
 *
 * @api
 */
interface BatchRepositoryInterface
{
    /**
     * Save batch
     *
     * @param BatchInterface $batch
     * @return BatchInterface
     * @throws CouldNotSaveException On unique-key violation (sku+batch_number+source_code)
     *                               or if remaining_qty > received_qty
     */
    public function save(BatchInterface $batch): BatchInterface;

    /**
     * Get batch by ID
     *
     * @param int $batchId
     * @return BatchInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $batchId): BatchInterface;

    /**
     * Get batch by SKU and batch number
     *
     * @param string $sku
     * @param string $batchNumber
     * @param string $sourceCode
     * @return BatchInterface
     * @throws NoSuchEntityException
     */
    public function getBySkuAndBatchNumber(string $sku, string $batchNumber, string $sourceCode = 'default'): BatchInterface;

    /**
     * Get batches by search criteria
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return BatchSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): BatchSearchResultsInterface;

    /**
     * Delete batch
     *
     * @param BatchInterface $batch
     * @return bool
     * @throws CouldNotDeleteException If the batch has transaction history
     */
    public function delete(BatchInterface $batch): bool;

    /**
     * Delete batch by ID
     *
     * @param int $batchId
     * @return bool
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException If the batch has transaction history
     */
    public function deleteById(int $batchId): bool;
}
