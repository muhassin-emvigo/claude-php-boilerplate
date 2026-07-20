<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Ui\DataProvider;

use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Rgd\CustomerCompliance\Model\ResourceModel\Field\CollectionFactory;

/**
 * Ui/Component data provider backing the Compliance Fields grid and form.
 *
 * Per the Design doc, the Fields grid is surfaced as a tab on the Group Config edit page, so
 * when a "config_id" request param is present the underlying collection is scoped to just the
 * fields belonging to that group config.
 */
class FieldDataProvider extends AbstractDataProvider
{
    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param RequestInterface $request
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly RequestInterface $request,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);

        $this->collection = $collectionFactory->create();

        $configId = $this->request->getParam('config_id');
        if ($configId !== null && $configId !== '') {
            $this->collection->addFieldToFilter('config_id', (int)$configId);
        }
    }
}
