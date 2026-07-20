<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Controller\Adminhtml\Document;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Rgd\CustomerCompliance\Api\AuditLoggerInterface;
use Rgd\CustomerCompliance\Api\DocumentStorageServiceInterface;

/**
 * Streams a compliance document to the admin browser.
 *
 * NOTE / CODE REVIEW FLAG: this controller intentionally returns the raw injected
 * {@see ResponseInterface} object rather than a Magento Result object (Page/Json/Redirect/etc).
 * This is because {@see DocumentStorageServiceInterface::streamDownload()} already builds the
 * full HTTP response (headers, body stream) internally as part of its own implementation, per
 * that service's design. Returning a separate Result object here would conflict with/duplicate
 * what the service already did to the response. This is an unusual controller shape purely as a
 * consequence of how the service layer was implemented, and is called out here for the same
 * reason it's called out in the service-layer code: it deviates from the conventional
 * "controller returns a Result" pattern and should be scrutinized in review.
 */
class Download extends Action
{
    public const ADMIN_RESOURCE = 'Rgd_CustomerCompliance::documents';

    /**
     * @param Context $context
     * @param DocumentStorageServiceInterface $documentStorageService
     * @param AuditLoggerInterface $auditLogger
     */
    public function __construct(
        Context $context,
        private readonly DocumentStorageServiceInterface $documentStorageService,
        private readonly AuditLoggerInterface $auditLogger
    ) {
        parent::__construct($context);
    }

    /**
     * Stream a compliance document file to the admin browser.
     *
     * @return ResponseInterface
     */
    public function execute(): ResponseInterface
    {
        $documentId = (int)$this->getRequest()->getParam('id');

        // Per the storage service's PHPDoc, the caller (this controller) owns emitting the
        // audit log entry for a document download; the service itself does not log it.
        $this->auditLogger->log(
            'admin',
            (int)$this->_session->getUser()->getId(),
            'document_downloaded',
            'document',
            $documentId,
            null
        );

        $this->documentStorageService->streamDownload($documentId);

        return $this->getResponse();
    }
}
