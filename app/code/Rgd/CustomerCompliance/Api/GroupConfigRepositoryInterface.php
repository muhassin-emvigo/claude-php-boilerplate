<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Api;

/**
 * Repository interface for customer-group compliance configuration records.
 *
 * @api
 */
interface GroupConfigRepositoryInterface
{
    /**
     * Save a group config.
     *
     * @param \Rgd\CustomerCompliance\Api\Data\GroupConfigInterface $groupConfig
     * @return \Rgd\CustomerCompliance\Api\Data\GroupConfigInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(Data\GroupConfigInterface $groupConfig): Data\GroupConfigInterface;

    /**
     * Get a group config by its id.
     *
     * @param int $configId
     * @return \Rgd\CustomerCompliance\Api\Data\GroupConfigInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $configId): Data\GroupConfigInterface;

    /**
     * Get a group config by the customer group it applies to.
     *
     * @param int $customerGroupId
     * @return \Rgd\CustomerCompliance\Api\Data\GroupConfigInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByCustomerGroupId(int $customerGroupId): Data\GroupConfigInterface;

    /**
     * Get a list of group configs matching the given search criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Rgd\CustomerCompliance\Api\Data\GroupConfigSearchResultsInterface
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
    ): Data\GroupConfigSearchResultsInterface;

    /**
     * Delete a group config.
     *
     * @param \Rgd\CustomerCompliance\Api\Data\GroupConfigInterface $groupConfig
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(Data\GroupConfigInterface $groupConfig): bool;

    /**
     * Delete a group config by its id.
     *
     * @param int $configId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $configId): bool;
}
