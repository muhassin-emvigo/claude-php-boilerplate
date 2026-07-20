<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Api\Data;

/**
 * Search results wrapper for DocumentInterface items.
 *
 * @api
 */
interface DocumentSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface
{
    /**
     * Get document items.
     *
     * @return \Rgd\CustomerCompliance\Api\Data\DocumentInterface[]
     */
    public function getItems(): array;

    /**
     * Set document items.
     *
     * @param \Rgd\CustomerCompliance\Api\Data\DocumentInterface[] $items
     * @return $this
     */
    public function setItems(array $items): self;
}
