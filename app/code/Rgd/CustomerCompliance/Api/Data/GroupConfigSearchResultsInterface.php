<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Api\Data;

/**
 * Search results wrapper for GroupConfigInterface items.
 *
 * @api
 */
interface GroupConfigSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface
{
    /**
     * Get group config items.
     *
     * @return \Rgd\CustomerCompliance\Api\Data\GroupConfigInterface[]
     */
    public function getItems(): array;

    /**
     * Set group config items.
     *
     * @param \Rgd\CustomerCompliance\Api\Data\GroupConfigInterface[] $items
     * @return $this
     */
    public function setItems(array $items): self;
}
