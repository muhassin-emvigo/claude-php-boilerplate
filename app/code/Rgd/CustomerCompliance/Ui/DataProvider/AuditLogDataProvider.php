<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Ui\DataProvider;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Rgd\CustomerCompliance\Model\ResourceModel\AuditLogEntry\CollectionFactory;

/**
 * Ui/Component data provider backing the read-only Audit Log grid.
 *
 * Mirrors the default sort applied by the audit log repository (newest first) so the grid
 * shows recent activity first before any user-driven column sort takes over. This is applied
 * directly on the collection here since the DataProvider talks to the collection, not the
 * repository.
 */
class AuditLogDataProvider extends AbstractDataProvider
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

        // Default sort: newest entries first. The Ui component's listing_top sorting
        // configuration (or an explicit user column-sort click) will override this via the
        // usual grid request parameters once applied.
        $this->collection->setOrder('created_at', 'DESC');
    }
}
