<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model\ResourceModel\FieldValue;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Rgd\CustomerCompliance\Model\FieldValue;
use Rgd\CustomerCompliance\Model\ResourceModel\FieldValue as FieldValueResourceModel;

/**
 * Collection of compliance field value records.
 */
class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(FieldValue::class, FieldValueResourceModel::class);
    }
}
