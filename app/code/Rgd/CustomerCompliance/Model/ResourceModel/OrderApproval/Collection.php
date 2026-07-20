<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model\ResourceModel\OrderApproval;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Rgd\CustomerCompliance\Model\OrderApproval;
use Rgd\CustomerCompliance\Model\ResourceModel\OrderApproval as OrderApprovalResourceModel;

/**
 * Collection of order approval (compliance hold) records.
 */
class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(OrderApproval::class, OrderApprovalResourceModel::class);
    }
}
