<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Api\Data;

/**
 * A single append-only audit log entry.
 *
 * Entries are written exclusively via
 * {@see \Rgd\CustomerCompliance\Api\AuditLoggerInterface::log()}; the setters below exist
 * for consistency and for use by the repository's internal save path, not for general
 * mutation of already-persisted entries.
 *
 * @api
 */
interface AuditLogEntryInterface
{
    /**
     * Get the log entry id.
     *
     * @return int|null
     */
    public function getLogId(): ?int;

    /**
     * Set the log entry id.
     *
     * @param int|null $logId
     * @return $this
     */
    public function setLogId(?int $logId): self;

    /**
     * Get the actor type (e.g. "customer", "admin", "system").
     *
     * @return string
     */
    public function getActorType(): string;

    /**
     * Set the actor type (e.g. "customer", "admin", "system").
     *
     * @param string $actorType
     * @return $this
     */
    public function setActorType(string $actorType): self;

    /**
     * Get the id of the acting customer/admin user, if applicable.
     *
     * @return int|null
     */
    public function getActorId(): ?int;

    /**
     * Set the id of the acting customer/admin user, if applicable.
     *
     * @param int|null $actorId
     * @return $this
     */
    public function setActorId(?int $actorId): self;

    /**
     * Get the short machine-readable action code.
     *
     * @return string
     */
    public function getAction(): string;

    /**
     * Set the short machine-readable action code.
     *
     * @param string $action
     * @return $this
     */
    public function setAction(string $action): self;

    /**
     * Get the type of the entity the action was performed against.
     *
     * @return string
     */
    public function getEntityType(): string;

    /**
     * Set the type of the entity the action was performed against.
     *
     * @param string $entityType
     * @return $this
     */
    public function setEntityType(string $entityType): self;

    /**
     * Get the id of the entity the action was performed against.
     *
     * @return string
     */
    public function getEntityId(): string;

    /**
     * Set the id of the entity the action was performed against.
     *
     * @param string $entityId
     * @return $this
     */
    public function setEntityId(string $entityId): self;

    /**
     * Get the free-text notes attached to this entry.
     *
     * @return string|null
     */
    public function getNotes(): ?string;

    /**
     * Set the free-text notes attached to this entry.
     *
     * @param string|null $notes
     * @return $this
     */
    public function setNotes(?string $notes): self;

    /**
     * Get the creation timestamp.
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * Set the creation timestamp.
     *
     * @param string|null $createdAt
     * @return $this
     */
    public function setCreatedAt(?string $createdAt): self;
}
