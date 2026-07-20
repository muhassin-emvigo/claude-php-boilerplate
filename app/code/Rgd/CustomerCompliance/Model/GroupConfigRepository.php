<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Rgd\CustomerCompliance\Api\Data\GroupConfigInterface;
use Rgd\CustomerCompliance\Api\Data\GroupConfigSearchResultsInterface;
use Rgd\CustomerCompliance\Api\Data\GroupConfigSearchResultsInterfaceFactory;
use Rgd\CustomerCompliance\Api\GroupConfigRepositoryInterface;
use Rgd\CustomerCompliance\Model\ResourceModel\GroupConfig as GroupConfigResource;
use Rgd\CustomerCompliance\Model\ResourceModel\GroupConfig\CollectionFactory as GroupConfigCollectionFactory;

/**
 * Repository for customer-group compliance configuration records.
 */
class GroupConfigRepository implements GroupConfigRepositoryInterface
{
    /**
     * @param GroupConfigResource $resource
     * @param GroupConfigFactory $groupConfigFactory
     * @param GroupConfigCollectionFactory $collectionFactory
     * @param GroupConfigSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        private readonly GroupConfigResource $resource,
        private readonly GroupConfigFactory $groupConfigFactory,
        private readonly GroupConfigCollectionFactory $collectionFactory,
        private readonly GroupConfigSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(GroupConfigInterface $groupConfig): GroupConfigInterface
    {
        try {
            $this->resource->save($groupConfig);
        } catch (AlreadyExistsException $e) {
            throw new AlreadyExistsException(
                __(
                    'A compliance configuration already exists for customer group "%1".',
                    $groupConfig->getCustomerGroupId()
                ),
                $e
            );
        } catch (\Exception $e) {
            if ($this->isDuplicateEntryException($e)) {
                throw new AlreadyExistsException(
                    __(
                        'A compliance configuration already exists for customer group "%1".',
                        $groupConfig->getCustomerGroupId()
                    ),
                    $e
                );
            }

            throw new CouldNotSaveException(
                __('Could not save the group config: %1', $e->getMessage()),
                $e
            );
        }

        return $groupConfig;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $configId): GroupConfigInterface
    {
        /** @var GroupConfigInterface $model */
        $model = $this->groupConfigFactory->create();
        $this->resource->load($model, $configId);

        if (!$model->getConfigId()) {
            throw new NoSuchEntityException(
                __('The group config with ID "%1" does not exist.', $configId)
            );
        }

        return $model;
    }

    /**
     * @inheritDoc
     */
    public function getByCustomerGroupId(int $customerGroupId): GroupConfigInterface
    {
        /** @var GroupConfigInterface $model */
        $model = $this->groupConfigFactory->create();
        $this->resource->load($model, $customerGroupId, 'customer_group_id');

        if (!$model->getConfigId()) {
            throw new NoSuchEntityException(
                __('No compliance configuration exists for customer group "%1".', $customerGroupId)
            );
        }

        return $model;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): GroupConfigSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();

        $this->collectionProcessor->process($searchCriteria, $collection);

        /** @var GroupConfigSearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function delete(GroupConfigInterface $groupConfig): bool
    {
        try {
            $this->resource->delete($groupConfig);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete the group config: %1', $e->getMessage()),
                $e
            );
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $configId): bool
    {
        return $this->delete($this->getById($configId));
    }

    /**
     * Determine whether the given exception represents a unique-constraint (duplicate entry) violation.
     *
     * @param \Exception $e
     * @return bool
     */
    private function isDuplicateEntryException(\Exception $e): bool
    {
        $message = $e->getMessage();

        return stripos($message, 'unique') !== false
            || stripos($message, 'duplicate') !== false
            || stripos($message, 'integrity constraint') !== false;
    }
}
