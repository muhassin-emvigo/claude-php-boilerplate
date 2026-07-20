<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Api;

/**
 * Repository interface for compliance field definition records.
 *
 * @api
 */
interface FieldRepositoryInterface
{
    /**
     * Save a field.
     *
     * @param \Rgd\CustomerCompliance\Api\Data\FieldInterface $field
     * @return \Rgd\CustomerCompliance\Api\Data\FieldInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(Data\FieldInterface $field): Data\FieldInterface;

    /**
     * Get a field by its id.
     *
     * @param int $fieldId
     * @return \Rgd\CustomerCompliance\Api\Data\FieldInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $fieldId): Data\FieldInterface;

    /**
     * Get a list of fields matching the given search criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Rgd\CustomerCompliance\Api\Data\FieldSearchResultsInterface
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
    ): Data\FieldSearchResultsInterface;

    /**
     * Get the list of fields belonging to a given group config.
     *
     * @param int $configId
     * @return \Rgd\CustomerCompliance\Api\Data\FieldSearchResultsInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getListByConfigId(int $configId): Data\FieldSearchResultsInterface;

    /**
     * Delete a field.
     *
     * @param \Rgd\CustomerCompliance\Api\Data\FieldInterface $field
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(Data\FieldInterface $field): bool;

    /**
     * Delete a field by its id.
     *
     * @param int $fieldId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $fieldId): bool;
}
