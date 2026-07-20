<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Api\Data;

/**
 * Search results wrapper for AuditLogEntryInterface items.
 *
 * @api
 */
interface AuditLogSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface
{
    /**
     * Get audit log entry items.
     *
     * @return \Rgd\CustomerCompliance\Api\Data\AuditLogEntryInterface[]
     */
    public function getItems(): array;

    /**
     * Set audit log entry items.
     *
     * @param \Rgd\CustomerCompliance\Api\Data\AuditLogEntryInterface[] $items
     * @return $this
     */
    public function setItems(array $items): self;
}
