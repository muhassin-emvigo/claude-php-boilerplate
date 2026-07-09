<?php
declare(strict_types=1);

namespace Rgd\Inventory\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Batch transaction resource model
 */
class BatchTransaction extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('rgd_inventory_batch_transaction', 'transaction_id');
    }
}
