<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Rgd\CustomerCompliance\Api\Data\FieldInterface;
use Rgd\CustomerCompliance\Api\Data\FieldSearchResultsInterface;
use Rgd\CustomerCompliance\Api\Data\FieldSearchResultsInterfaceFactory;
use Rgd\CustomerCompliance\Api\FieldRepositoryInterface;
use Rgd\CustomerCompliance\Model\ResourceModel\Field as FieldResource;
use Rgd\CustomerCompliance\Model\ResourceModel\Field\CollectionFactory as FieldCollectionFactory;

/**
 * Repository for compliance field definition records.
 */
class FieldRepository implements FieldRepositoryInterface
{
    /**
     * @param FieldResource $resource
     * @param FieldFactory $fieldFactory
     * @param FieldCollectionFactory $collectionFactory
     * @param FieldSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     */
    public function __construct(
        private readonly FieldResource $resource,
        private readonly FieldFactory $fieldFactory,
        private readonly FieldCollectionFactory $collectionFactory,
        private readonly FieldSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly FilterBuilder $filterBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(FieldInterface $field): FieldInterface
    {
        try {
            $this->resource->save($field);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save the field: %1', $e->getMessage()),
                $e
            );
        }

        return $field;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $fieldId): FieldInterface
    {
        /** @var FieldInterface $model */
        $model = $this->fieldFactory->create();
        $this->resource->load($model, $fieldId);

        if (!$model->getFieldId()) {
            throw new NoSuchEntityException(
                __('The field with ID "%1" does not exist.', $fieldId)
            );
        }

        return $model;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): FieldSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();

        $this->collectionProcessor->process($searchCriteria, $collection);

        /** @var FieldSearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function getListByConfigId(int $configId): FieldSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('config_id', $configId);
        $collection->addFieldToFilter('is_active', 1);
        $collection->setOrder('sort_order', 'ASC');

        $configIdFilter = $this->filterBuilder
            ->setField('config_id')
            ->setValue($configId)
            ->setConditionType('eq')
            ->create();

        $isActiveFilter = $this->filterBuilder
            ->setField('is_active')
            ->setValue(1)
            ->setConditionType('eq')
            ->create();

        $sortOrder = $this->sortOrderBuilder
            ->setField('sort_order')
            ->setDirection(\Magento\Framework\Api\SortOrder::SORT_ASC)
            ->create();

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilters([$configIdFilter])
            ->addFilters([$isActiveFilter])
            ->setSortOrders([$sortOrder])
            ->create();

        /** @var FieldSearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function delete(FieldInterface $field): bool
    {
        try {
            $this->resource->delete($field);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete the field: %1', $e->getMessage()),
                $e
            );
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $fieldId): bool
    {
        return $this->delete($this->getById($fieldId));
    }
}
