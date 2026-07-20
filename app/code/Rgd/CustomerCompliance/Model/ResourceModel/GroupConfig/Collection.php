<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model\ResourceModel\GroupConfig;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Rgd\CustomerCompliance\Model\GroupConfig;
use Rgd\CustomerCompliance\Model\ResourceModel\GroupConfig as GroupConfigResourceModel;

/**
 * Collection of compliance group config records.
 */
class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(GroupConfig::class, GroupConfigResourceModel::class);
    }
}
