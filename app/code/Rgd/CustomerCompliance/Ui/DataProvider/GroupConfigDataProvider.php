<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Ui\DataProvider;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Rgd\CustomerCompliance\Model\ResourceModel\GroupConfig\CollectionFactory;

/**
 * Ui/Component data provider backing the Group Configuration grid and form.
 */
class GroupConfigDataProvider extends AbstractDataProvider
{
    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);

        $this->collection = $collectionFactory->create();
    }
}
