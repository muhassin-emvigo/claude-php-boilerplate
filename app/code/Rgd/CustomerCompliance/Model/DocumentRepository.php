<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Rgd\CustomerCompliance\Api\Data\DocumentInterface;
use Rgd\CustomerCompliance\Api\Data\DocumentSearchResultsInterface;
use Rgd\CustomerCompliance\Api\Data\DocumentSearchResultsInterfaceFactory;
use Rgd\CustomerCompliance\Api\DocumentRepositoryInterface;
use Rgd\CustomerCompliance\Model\ResourceModel\Document as DocumentResource;
use Rgd\CustomerCompliance\Model\ResourceModel\Document\CollectionFactory as DocumentCollectionFactory;

/**
 * Repository for stored compliance document records.
 */
class DocumentRepository implements DocumentRepositoryInterface
{
    /**
     * @param DocumentResource $resource
     * @param DocumentFactory $documentFactory
     * @param DocumentCollectionFactory $collectionFactory
     * @param DocumentSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     */
    public function __construct(
        private readonly DocumentResource $resource,
        private readonly DocumentFactory $documentFactory,
        private readonly DocumentCollectionFactory $collectionFactory,
        private readonly DocumentSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly FilterBuilder $filterBuilder
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(DocumentInterface $document): DocumentInterface
    {
        try {
            $this->resource->save($document);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save the document: %1', $e->getMessage()),
                $e
            );
        }

        return $document;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $documentId): DocumentInterface
    {
        /** @var DocumentInterface $model */
        $model = $this->documentFactory->create();
        $this->resource->load($model, $documentId);

        if (!$model->getDocumentId()) {
            throw new NoSuchEntityException(
                __('The document with ID "%1" does not exist.', $documentId)
            );
        }

        return $model;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): DocumentSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();

        $this->collectionProcessor->process($searchCriteria, $collection);

        /** @var DocumentSearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function getCurrentForCustomer(int $customerId): DocumentSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);
        $collection->addFieldToFilter('is_current', 1);

        if ($collection->getSize() === 0) {
            throw new NoSuchEntityException(
                __('No current compliance documents were found for customer "%1".', $customerId)
            );
        }

        $customerIdFilter = $this->filterBuilder
            ->setField('customer_id')
            ->setValue($customerId)
            ->setConditionType('eq')
            ->create();

        $isCurrentFilter = $this->filterBuilder
            ->setField('is_current')
            ->setValue(1)
            ->setConditionType('eq')
            ->create();

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilters([$customerIdFilter])
            ->addFilters([$isCurrentFilter])
            ->create();

        /** @var DocumentSearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }
}
