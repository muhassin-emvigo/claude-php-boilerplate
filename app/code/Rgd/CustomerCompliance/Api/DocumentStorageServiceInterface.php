<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Api;

/**
 * Handles physical storage and secure retrieval of uploaded compliance documents.
 *
 * @api
 */
interface DocumentStorageServiceInterface
{
    /**
     * Store an uploaded file for a customer/field and persist its document record.
     *
     * Optionally associates the document with an order.
     *
     * @param int $customerId
     * @param int $fieldId
     * @param int|null $orderId
     * @param \Rgd\CustomerCompliance\Api\Data\UploadedFileInterface $file
     * @return \Rgd\CustomerCompliance\Api\Data\DocumentInterface
     * @throws \Rgd\CustomerCompliance\Exception\BusinessRuleException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function store(
        int $customerId,
        int $fieldId,
        ?int $orderId,
        \Rgd\CustomerCompliance\Api\Data\UploadedFileInterface $file
    ): Data\DocumentInterface;

    /**
     * Get a time-limited, signed secure URL for downloading a stored document.
     *
     * @param int $documentId
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getSecureUrl(int $documentId): string;

    /**
     * Stream a stored document directly to the current HTTP response.
     *
     * @param int $documentId
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function streamDownload(int $documentId): void;
}
