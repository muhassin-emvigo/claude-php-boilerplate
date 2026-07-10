<?php
declare(strict_types=1);

namespace Rgd\Inventory\Ui\DataProvider;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Rgd\Inventory\Model\ResourceModel\Batch\CollectionFactory;

/**
 * Data provider for batch listing (admin grid) UI component.
 *
 * Deliberately does not override getData() — the inherited
 * AbstractDataProvider::getData() returns $this->getCollection()->toArray(),
 * which already produces the {items, totalRecords} shape the grid JS component
 * expects. (Contrast with BatchDataProvider, the form data provider, which
 * needs the different [$id => $itemData] shape and overrides getData()
 * accordingly.)
 */
class BatchListingDataProvider extends AbstractDataProvider
{
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = [],
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }
}
