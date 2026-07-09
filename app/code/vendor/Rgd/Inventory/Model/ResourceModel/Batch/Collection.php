<?php
declare(strict_types=1);

namespace Rgd\Inventory\Model\ResourceModel\Batch;

use Rgd\Inventory\Model\Data\Batch;
use Rgd\Inventory\Model\ResourceModel\Batch as BatchResourceModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Batch collection
 */
class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(Batch::class, BatchResourceModel::class);
    }
}
