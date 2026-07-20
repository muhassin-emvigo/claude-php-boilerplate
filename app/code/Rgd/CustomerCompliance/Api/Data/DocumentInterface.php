<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Api\Data;

/**
 * A stored compliance document (uploaded file) record.
 *
 * @api
 */
interface DocumentInterface
{
    /**
     * Get the document id.
     *
     * @return int|null
     */
    public function getDocumentId(): ?int;

    /**
     * Set the document id.
     *
     * @param int|null $documentId
     * @return $this
     */
    public function setDocumentId(?int $documentId): self;

    /**
     * Get the owning customer id.
     *
     * @return int
     */
    public function getCustomerId(): int;

    /**
     * Set the owning customer id.
     *
     * @param int $customerId
     * @return $this
     */
    public function setCustomerId(int $customerId): self;

    /**
     * Get the id of the field this document was uploaded for.
     *
     * @return int
     */
    public function getFieldId(): int;

    /**
     * Set the id of the field this document was uploaded for.
     *
     * @param int $fieldId
     * @return $this
     */
    public function setFieldId(int $fieldId): self;

    /**
     * Get the id of the order this document is associated with, if any.
     *
     * @return int|null
     */
    public function getOrderId(): ?int;

    /**
     * Set the id of the order this document is associated with, if any.
     *
     * @param int|null $orderId
     * @return $this
     */
    public function setOrderId(?int $orderId): self;

    /**
     * Get the original uploaded file name.
     *
     * @return string
     */
    public function getFileName(): string;

    /**
     * Set the original uploaded file name.
     *
     * @param string $fileName
     * @return $this
     */
    public function setFileName(string $fileName): self;

    /**
     * Get the server-side storage path of the file.
     *
     * @return string
     */
    public function getFilePath(): string;

    /**
     * Set the server-side storage path of the file.
     *
     * @param string $filePath
     * @return $this
     */
    public function setFilePath(string $filePath): self;

    /**
     * Get the file's MIME content type.
     *
     * @return string
     */
    public function getContentType(): string;

    /**
     * Set the file's MIME content type.
     *
     * @param string $contentType
     * @return $this
     */
    public function setContentType(string $contentType): self;

    /**
     * Get the file size, in bytes.
     *
     * @return int
     */
    public function getFileSize(): int;

    /**
     * Set the file size, in bytes.
     *
     * @param int $fileSize
     * @return $this
     */
    public function setFileSize(int $fileSize): self;

    /**
     * Get the document version number.
     *
     * @return int
     */
    public function getVersion(): int;

    /**
     * Set the document version number.
     *
     * @param int $version
     * @return $this
     */
    public function setVersion(int $version): self;

    /**
     * Whether this is the current (latest, active) version of the document.
     *
     * @return bool
     */
    public function isCurrent(): bool;

    /**
     * Set whether this is the current (latest, active) version of the document.
     *
     * @param bool $current
     * @return $this
     */
    public function setCurrent(bool $current): self;

    /**
     * Get the file checksum (e.g. sha256), used for integrity verification.
     *
     * @return string
     */
    public function getChecksum(): string;

    /**
     * Set the file checksum (e.g. sha256), used for integrity verification.
     *
     * @param string $checksum
     * @return $this
     */
    public function setChecksum(string $checksum): self;

    /**
     * Get the creation timestamp.
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;
}
