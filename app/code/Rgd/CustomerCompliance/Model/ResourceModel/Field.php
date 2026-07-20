<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Resource model for the compliance field definition table.
 */
class Field extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init('rgd_customercompliance_field', 'field_id');
    }
}
