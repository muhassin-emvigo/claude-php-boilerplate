<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Controller\Document;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\NotFoundException;
use Rgd\CustomerCompliance\Api\AuditLoggerInterface;
use Rgd\CustomerCompliance\Api\DocumentRepositoryInterface;
use Rgd\CustomerCompliance\Api\DocumentStorageServiceInterface;

/**
 * Streams a stored compliance document back to its owning, logged-in customer.
 *
 * Ownership is strictly enforced and non-current (superseded) document versions are never
 * customer-downloadable; both failure modes are surfaced as a plain 404 (NotFoundException)
 * rather than a 403, so an authenticated customer probing another customer's document ids
 * cannot use the response to infer whether a given document id exists at all.
 */
class Download extends Action implements HttpGetActionInterface
{
    /**
     * @param Context $context
     * @param CustomerSession $customerSession
     * @param DocumentRepositoryInterface $documentRepository
     * @param DocumentStorageServiceInterface $documentStorageService
     * @param AuditLoggerInterface $auditLogger
     */
    public function __construct(
        Context $context,
        private readonly CustomerSession $customerSession,
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly DocumentStorageServiceInterface $documentStorageService,
        private readonly AuditLoggerInterface $auditLogger
    ) {
        parent::__construct($context);
    }

    /**
     * Stream a compliance document file to its owning customer.
     *
     * @return \Magento\Framework\Controller\ResultInterface|void
     */
    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            /** @var Redirect $redirect */
            $redirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
            return $redirect->setPath('customer/account/login');
        }

        $id = (int)$this->getRequest()->getParam('id');

        try {
            $document = $this->documentRepository->getById($id);
        } catch (NoSuchEntityException $e) {
            throw new NotFoundException(__('Document not found.'));
        }

        if ((int)$document->getCustomerId() !== (int)$this->customerSession->getCustomerId()) {
            // 403-as-404: don't leak the existence of another customer's document.
            throw new NotFoundException(__('Document not found.'));
        }

        if (!$document->isCurrent()) {
            // Per the Design doc's default policy, superseded document versions are not
            // customer-downloadable.
            throw new NotFoundException(__('Document not found.'));
        }

        $this->auditLogger->log(
            'customer',
            $this->customerSession->getCustomerId(),
            'document_downloaded',
            'document',
            (string)$id,
            null
        );

        // streamDownload() writes directly to the HTTP response and returns void; there is no
        // ResultInterface to return here (same awkwardness as the admin download controller).
        $this->documentStorageService->streamDownload($id);
    }
}
