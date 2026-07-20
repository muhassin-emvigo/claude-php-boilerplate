<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Block\Account;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Rgd\CustomerCompliance\Api\Data\DocumentInterface;
use Rgd\CustomerCompliance\Api\DocumentRepositoryInterface;

/**
 * Exposes the logged-in customer's current (latest, active) compliance documents to the
 * `account/documents.phtml` template.
 */
class Documents extends Template
{
    /**
     * @param Context $context
     * @param DocumentRepositoryInterface $documentRepository
     * @param CustomerSession $customerSession
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly CustomerSession $customerSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get the current customer's current compliance documents.
     *
     * @return DocumentInterface[]
     */
    public function getDocuments(): array
    {
        $customerId = (int)$this->customerSession->getCustomerId();
        if ($customerId <= 0) {
            return [];
        }

        try {
            $searchResults = $this->documentRepository->getCurrentForCustomer($customerId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return [];
        }

        return $searchResults->getItems();
    }

    /**
     * Build the URL to download a given document.
     *
     * @param DocumentInterface $document
     * @return string
     */
    public function getDownloadUrl(DocumentInterface $document): string
    {
        return $this->getUrl('customercompliance/document/download', ['id' => $document->getDocumentId()]);
    }
}
