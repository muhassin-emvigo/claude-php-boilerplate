<?php
declare(strict_types=1);

namespace Rgd\Inventory\Ui\DataProvider;

use Magento\Framework\Api\Filter;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Rgd\Inventory\Model\ResourceModel\Batch\Collection;
use Rgd\Inventory\Model\ResourceModel\Batch\CollectionFactory;

/**
 * Data provider for batch form UI component
 */
class BatchDataProvider extends AbstractDataProvider
{
    /**
     * @var Collection
     */
    protected $collection;

    /**
     * @var array<int|string, array<string, mixed>>|null
     */
    private ?array $loadedData = null;

    /**
     * Whether a real primary-key filter (i.e. not the empty/null filter
     * Form::getDataSourceData() always injects for a "new" record) has been
     * applied via addFilter(). Used by getData() to avoid loading the entire
     * unfiltered batch collection on "Add New Batch".
     */
    private bool $hasRealFilter = false;

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

    /**
     * Skip null/empty filters — Form::getDataSourceData() always adds one even for "new" records.
     */
    public function addFilter(Filter $filter): void
    {
        $value = $filter->getValue();
        if ($value === null || $value === '') {
            return;
        }

        $this->hasRealFilter = true;
        $this->loadedData = null;
        parent::addFilter($filter);
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function getData(): array
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }

        // "Add New Batch" always injects an empty/null primary-key filter
        // (skipped above, in addFilter()) but never a real one. Without this
        // guard, execution falls through and hydrates the entire unfiltered
        // batch table just to back an empty "new record" form.
        if (!$this->hasRealFilter) {
            $this->loadedData = [];
            return $this->loadedData;
        }

        $this->loadedData = [];

        if (!$this->getCollection()->isLoaded()) {
            $this->getCollection()->load();
        }

        foreach ($this->getCollection() as $item) {
            $id = $item->getData($this->getPrimaryFieldName());
            if ($id !== null) {
                $this->loadedData[$id] = $item->getData();
            }
        }

        return $this->loadedData;
    }
}
