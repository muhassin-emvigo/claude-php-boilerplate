<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model\ResourceModel\AuditLogEntry;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Rgd\CustomerCompliance\Model\AuditLogEntry;
use Rgd\CustomerCompliance\Model\ResourceModel\AuditLogEntry as AuditLogEntryResourceModel;

/**
 * Collection of compliance audit log entries.
 */
class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(AuditLogEntry::class, AuditLogEntryResourceModel::class);
    }
}
