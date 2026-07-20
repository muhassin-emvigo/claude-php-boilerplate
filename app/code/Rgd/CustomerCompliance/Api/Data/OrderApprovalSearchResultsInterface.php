<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Api\Data;

/**
 * Search results wrapper for OrderApprovalInterface items.
 *
 * @api
 */
interface OrderApprovalSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface
{
    /**
     * Get order approval items.
     *
     * @return \Rgd\CustomerCompliance\Api\Data\OrderApprovalInterface[]
     */
    public function getItems(): array;

    /**
     * Set order approval items.
     *
     * @param \Rgd\CustomerCompliance\Api\Data\OrderApprovalInterface[] $items
     * @return $this
     */
    public function setItems(array $items): self;
}
