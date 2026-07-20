<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Api;

/**
 * Repository interface for order approval (compliance hold) records.
 *
 * @api
 */
interface OrderApprovalRepositoryInterface
{
    /**
     * Save an order approval record.
     *
     * @param \Rgd\CustomerCompliance\Api\Data\OrderApprovalInterface $orderApproval
     * @return \Rgd\CustomerCompliance\Api\Data\OrderApprovalInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(Data\OrderApprovalInterface $orderApproval): Data\OrderApprovalInterface;

    /**
     * Get an order approval record by its id.
     *
     * @param int $approvalId
     * @return \Rgd\CustomerCompliance\Api\Data\OrderApprovalInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $approvalId): Data\OrderApprovalInterface;

    /**
     * Get an order approval record by the order it belongs to.
     *
     * @param int $orderId
     * @return \Rgd\CustomerCompliance\Api\Data\OrderApprovalInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByOrderId(int $orderId): Data\OrderApprovalInterface;

    /**
     * Get a list of order approval records matching the given search criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Rgd\CustomerCompliance\Api\Data\OrderApprovalSearchResultsInterface
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
    ): Data\OrderApprovalSearchResultsInterface;
}
