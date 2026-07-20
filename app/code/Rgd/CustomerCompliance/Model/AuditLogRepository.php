<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Rgd\CustomerCompliance\Api\AuditLogRepositoryInterface;
use Rgd\CustomerCompliance\Api\Data\AuditLogSearchResultsInterface;
use Rgd\CustomerCompliance\Api\Data\AuditLogSearchResultsInterfaceFactory;
use Rgd\CustomerCompliance\Model\ResourceModel\AuditLogEntry\CollectionFactory as AuditLogEntryCollectionFactory;

/**
 * Read-only repository for compliance audit log entries.
 */
class AuditLogRepository implements AuditLogRepositoryInterface
{
    /**
     * @param AuditLogEntryCollectionFactory $collectionFactory
     * @param AuditLogSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        private readonly AuditLogEntryCollectionFactory $collectionFactory,
        private readonly AuditLogSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): AuditLogSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();

        if (!count($searchCriteria->getSortOrders() ?? [])) {
            $collection->setOrder('created_at', 'DESC');
        }

        $this->collectionProcessor->process($searchCriteria, $collection);

        /** @var AuditLogSearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }
}
