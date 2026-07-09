<?php
declare(strict_types=1);

namespace Rgd\Inventory\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Search results interface for batch queries
 *
 * @api
 */
interface BatchSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get items
     *
     * @return BatchInterface[]
     */
    public function getItems();

    /**
     * Set items
     *
     * @param BatchInterface[] $items
     * @return self
     */
    public function setItems(array $items);
}
