<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Api;

/**
 * Repository interface for stored compliance document records.
 *
 * @api
 */
interface DocumentRepositoryInterface
{
    /**
     * Save a document.
     *
     * @param \Rgd\CustomerCompliance\Api\Data\DocumentInterface $document
     * @return \Rgd\CustomerCompliance\Api\Data\DocumentInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(Data\DocumentInterface $document): Data\DocumentInterface;

    /**
     * Get a document by its id.
     *
     * @param int $documentId
     * @return \Rgd\CustomerCompliance\Api\Data\DocumentInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $documentId): Data\DocumentInterface;

    /**
     * Get a list of documents matching the given search criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Rgd\CustomerCompliance\Api\Data\DocumentSearchResultsInterface
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
    ): Data\DocumentSearchResultsInterface;

    /**
     * Get the current (latest, active) documents on file for a customer.
     *
     * @param int $customerId
     * @return \Rgd\CustomerCompliance\Api\Data\DocumentSearchResultsInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCurrentForCustomer(int $customerId): Data\DocumentSearchResultsInterface;
}
