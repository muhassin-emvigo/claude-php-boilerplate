<?php
declare(strict_types=1);

namespace Rgd\Inventory\Ui\DataProvider;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Rgd\Inventory\Model\ResourceModel\Batch\CollectionFactory;
use Rgd\Inventory\Model\ResourceModel\Batch\Collection;

/**
 * Data provider for batch form UI component
 */
class BatchDataProvider extends AbstractDataProvider
{
    /**
     * @var Collection
     */
    protected $collection;

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

    public function getData()
    {
        if (!$this->getCollection()->isLoaded()) {
            $this->getCollection()->load();
        }

        $data = [];
        foreach ($this->getCollection() as $item) {
            $data[$item->getId()] = $item->getData();
        }

        return $data;
    }
}
