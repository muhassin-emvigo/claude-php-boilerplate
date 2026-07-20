<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model\ResourceModel\Document;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Rgd\CustomerCompliance\Model\Document;
use Rgd\CustomerCompliance\Model\ResourceModel\Document as DocumentResourceModel;

/**
 * Collection of compliance document records.
 */
class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(Document::class, DocumentResourceModel::class);
    }
}
