<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Resource model for the compliance field value table.
 */
class FieldValue extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init('rgd_customercompliance_field_value', 'value_id');
    }
}
