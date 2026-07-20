<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Api\Data;

/**
 * Search results wrapper for FieldInterface items.
 *
 * @api
 */
interface FieldSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface
{
    /**
     * Get field items.
     *
     * @return \Rgd\CustomerCompliance\Api\Data\FieldInterface[]
     */
    public function getItems(): array;

    /**
     * Set field items.
     *
     * @param \Rgd\CustomerCompliance\Api\Data\FieldInterface[] $items
     * @return $this
     */
    public function setItems(array $items): self;
}
