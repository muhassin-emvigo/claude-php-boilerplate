<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Rgd\CustomerCompliance\Api\Data\OrderApprovalInterface;
use Rgd\CustomerCompliance\Api\Data\OrderApprovalSearchResultsInterface;
use Rgd\CustomerCompliance\Api\Data\OrderApprovalSearchResultsInterfaceFactory;
use Rgd\CustomerCompliance\Api\OrderApprovalRepositoryInterface;
use Rgd\CustomerCompliance\Model\ResourceModel\OrderApproval as OrderApprovalResource;
use Rgd\CustomerCompliance\Model\ResourceModel\OrderApproval\CollectionFactory as OrderApprovalCollectionFactory;

/**
 * Repository for order approval (compliance hold) records.
 */
class OrderApprovalRepository implements OrderApprovalRepositoryInterface
{
    /**
     * @param OrderApprovalResource $resource
     * @param OrderApprovalFactory $orderApprovalFactory
     * @param OrderApprovalCollectionFactory $collectionFactory
     * @param OrderApprovalSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        private readonly OrderApprovalResource $resource,
        private readonly OrderApprovalFactory $orderApprovalFactory,
        private readonly OrderApprovalCollectionFactory $collectionFactory,
        private readonly OrderApprovalSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(OrderApprovalInterface $orderApproval): OrderApprovalInterface
    {
        try {
            $this->resource->save($orderApproval);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save the order approval: %1', $e->getMessage()),
                $e
            );
        }

        return $orderApproval;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $approvalId): OrderApprovalInterface
    {
        /** @var OrderApprovalInterface $model */
        $model = $this->orderApprovalFactory->create();
        $this->resource->load($model, $approvalId);

        if (!$model->getApprovalId()) {
            throw new NoSuchEntityException(
                __('The order approval with ID "%1" does not exist.', $approvalId)
            );
        }

        return $model;
    }

    /**
     * @inheritDoc
     */
    public function getByOrderId(int $orderId): OrderApprovalInterface
    {
        /** @var OrderApprovalInterface $model */
        $model = $this->orderApprovalFactory->create();
        $this->resource->load($model, $orderId, 'order_id');

        if (!$model->getApprovalId()) {
            throw new NoSuchEntityException(
                __('No order approval record exists for order "%1".', $orderId)
            );
        }

        return $model;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): OrderApprovalSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();

        $this->collectionProcessor->process($searchCriteria, $collection);

        /** @var OrderApprovalSearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }
}
