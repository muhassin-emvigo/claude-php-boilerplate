<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Resource model for the order approval (compliance hold) table.
 */
class OrderApproval extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init('rgd_customercompliance_order_approval', 'approval_id');
    }
}
