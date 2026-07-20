<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model;

use Magento\Framework\Model\AbstractModel;
use Rgd\CustomerCompliance\Api\Data\AuditLogEntryInterface;
use Rgd\CustomerCompliance\Model\ResourceModel\AuditLogEntry as AuditLogEntryResourceModel;

/**
 * Compliance audit log entry model.
 */
class AuditLogEntry extends AbstractModel implements AuditLogEntryInterface
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(AuditLogEntryResourceModel::class);
    }

    /**
     * @inheritDoc
     */
    public function getLogId(): ?int
    {
        $value = $this->getData('log_id');

        return $value !== null ? (int)$value : null;
    }

    /**
     * @inheritDoc
     */
    public function getActorType(): string
    {
        return (string)$this->getData('actor_type');
    }

    /**
     * @inheritDoc
     */
    public function setActorType(string $actorType): AuditLogEntryInterface
    {
        return $this->setData('actor_type', $actorType);
    }

    /**
     * @inheritDoc
     */
    public function getActorId(): ?int
    {
        $value = $this->getData('actor_id');

        return $value !== null ? (int)$value : null;
    }

    /**
     * @inheritDoc
     */
    public function setActorId(?int $actorId): AuditLogEntryInterface
    {
        return $this->setData('actor_id', $actorId);
    }

    /**
     * @inheritDoc
     */
    public function getAction(): string
    {
        return (string)$this->getData('action');
    }

    /**
     * @inheritDoc
     */
    public function setAction(string $action): AuditLogEntryInterface
    {
        return $this->setData('action', $action);
    }

    /**
     * @inheritDoc
     */
    public function getEntityType(): string
    {
        return (string)$this->getData('entity_type');
    }

    /**
     * @inheritDoc
     */
    public function setEntityType(string $entityType): AuditLogEntryInterface
    {
        return $this->setData('entity_type', $entityType);
    }

    /**
     * @inheritDoc
     */
    public function getEntityId(): ?int
    {
        $value = $this->getData('entity_id');

        return $value !== null ? (int)$value : null;
    }

    /**
     * @inheritDoc
     */
    public function setEntityId(?int $entityId): AuditLogEntryInterface
    {
        return $this->setData('entity_id', $entityId);
    }

    /**
     * @inheritDoc
     */
    public function getNotes(): ?string
    {
        $value = $this->getData('notes');

        return $value !== null ? (string)$value : null;
    }

    /**
     * @inheritDoc
     */
    public function setNotes(?string $notes): AuditLogEntryInterface
    {
        return $this->setData('notes', $notes);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): ?string
    {
        $value = $this->getData('created_at');

        return $value !== null ? (string)$value : null;
    }
}
