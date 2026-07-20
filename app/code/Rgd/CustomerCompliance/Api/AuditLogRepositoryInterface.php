<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Api;

/**
 * Read-only repository interface for audit log entries.
 *
 * Audit log entries are append-only: this repository intentionally exposes
 * no save() or delete() methods. New entries are written exclusively via
 * {@see \Rgd\CustomerCompliance\Api\AuditLoggerInterface::log()}.
 *
 * @api
 */
interface AuditLogRepositoryInterface
{
    /**
     * Get a list of audit log entries matching the given search criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Rgd\CustomerCompliance\Api\Data\AuditLogSearchResultsInterface
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
    ): Data\AuditLogSearchResultsInterface;
}
