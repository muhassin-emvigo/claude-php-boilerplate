<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model\ResourceModel\Field;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Rgd\CustomerCompliance\Model\Field;
use Rgd\CustomerCompliance\Model\ResourceModel\Field as FieldResourceModel;

/**
 * Collection of compliance field definition records.
 */
class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(Field::class, FieldResourceModel::class);
    }
}
