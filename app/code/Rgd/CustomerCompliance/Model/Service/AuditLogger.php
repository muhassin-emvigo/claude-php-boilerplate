<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model\Service;

use Psr\Log\LoggerInterface;
use Rgd\CustomerCompliance\Api\AuditLoggerInterface;
use Rgd\CustomerCompliance\Model\AuditLogEntryFactory;
use Rgd\CustomerCompliance\Model\ResourceModel\AuditLogEntry as AuditLogEntryResource;

/**
 * Writes append-only audit log entries for compliance-relevant actions.
 */
class AuditLogger implements AuditLoggerInterface
{
    /**
     * @param AuditLogEntryFactory $auditLogEntryFactory
     * @param AuditLogEntryResource $resource
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly AuditLogEntryFactory $auditLogEntryFactory,
        private readonly AuditLogEntryResource $resource,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function log(
        string $actorType,
        ?int $actorId,
        string $action,
        string $entityType,
        string $entityId,
        ?string $notes
    ): void {
        try {
            $entry = $this->auditLogEntryFactory->create();
            $entry->setActorType($actorType)
                ->setActorId($actorId)
                ->setAction($action)
                ->setEntityType($entityType)
                ->setEntityId(is_numeric($entityId) ? (int)$entityId : null)
                ->setNotes($notes);

            $this->resource->save($entry);
        } catch (\Throwable $e) {
            // Deliberate resilience choice: an audit log write failure must never block or
            // fail the business operation that triggered it. Swallow and log only.
            $this->logger->error(
                'Failed to write compliance audit log entry: ' . $e->getMessage(),
                ['exception' => $e, 'action' => $action, 'entity_type' => $entityType, 'entity_id' => $entityId]
            );
        }
    }
}
