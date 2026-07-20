<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Api;

/**
 * Writes append-only audit log entries for compliance-relevant actions.
 *
 * This is the sole write path for audit log entries; {@see AuditLogRepositoryInterface}
 * is intentionally read-only.
 *
 * @api
 */
interface AuditLoggerInterface
{
    /**
     * Record an audit log entry.
     *
     * @param string $actorType e.g. "customer", "admin", "system".
     * @param int|null $actorId Id of the acting customer/admin user, if applicable.
     * @param string $action Short machine-readable action code, e.g. "document_uploaded".
     * @param string $entityType Type of the entity the action was performed against.
     * @param string $entityId Id of the entity the action was performed against.
     * @param string|null $notes Optional free-text context.
     * @return void
     */
    public function log(
        string $actorType,
        ?int $actorId,
        string $action,
        string $entityType,
        string $entityId,
        ?string $notes
    ): void;
}
