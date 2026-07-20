<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model;

use Magento\Framework\Model\AbstractModel;
use Rgd\CustomerCompliance\Api\Data\DocumentInterface;
use Rgd\CustomerCompliance\Model\ResourceModel\Document as DocumentResourceModel;

/**
 * Customer compliance document model.
 */
class Document extends AbstractModel implements DocumentInterface
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(DocumentResourceModel::class);
    }

    /**
     * @inheritDoc
     */
    public function getDocumentId(): ?int
    {
        $value = $this->getData('document_id');

        return $value !== null ? (int)$value : null;
    }

    /**
     * @inheritDoc
     */
    public function setDocumentId(?int $documentId): DocumentInterface
    {
        return $this->setData('document_id', $documentId);
    }

    /**
     * @inheritDoc
     */
    public function getCustomerId(): int
    {
        return (int)$this->getData('customer_id');
    }

    /**
     * @inheritDoc
     */
    public function setCustomerId(int $customerId): DocumentInterface
    {
        return $this->setData('customer_id', $customerId);
    }

    /**
     * @inheritDoc
     */
    public function getFieldId(): int
    {
        return (int)$this->getData('field_id');
    }

    /**
     * @inheritDoc
     */
    public function setFieldId(int $fieldId): DocumentInterface
    {
        return $this->setData('field_id', $fieldId);
    }

    /**
     * @inheritDoc
     */
    public function getOrderId(): ?int
    {
        $value = $this->getData('order_id');

        return $value !== null ? (int)$value : null;
    }

    /**
     * @inheritDoc
     */
    public function setOrderId(?int $orderId): DocumentInterface
    {
        return $this->setData('order_id', $orderId);
    }

    /**
     * @inheritDoc
     */
    public function getFileName(): string
    {
        return (string)$this->getData('file_name');
    }

    /**
     * @inheritDoc
     */
    public function setFileName(string $fileName): DocumentInterface
    {
        return $this->setData('file_name', $fileName);
    }

    /**
     * @inheritDoc
     */
    public function getFilePath(): string
    {
        return (string)$this->getData('file_path');
    }

    /**
     * @inheritDoc
     */
    public function setFilePath(string $filePath): DocumentInterface
    {
        return $this->setData('file_path', $filePath);
    }

    /**
     * @inheritDoc
     */
    public function getContentType(): string
    {
        return (string)$this->getData('content_type');
    }

    /**
     * @inheritDoc
     */
    public function setContentType(string $contentType): DocumentInterface
    {
        return $this->setData('content_type', $contentType);
    }

    /**
     * @inheritDoc
     */
    public function getFileSize(): int
    {
        return (int)$this->getData('file_size');
    }

    /**
     * @inheritDoc
     */
    public function setFileSize(int $fileSize): DocumentInterface
    {
        return $this->setData('file_size', $fileSize);
    }

    /**
     * @inheritDoc
     */
    public function getVersion(): int
    {
        return (int)$this->getData('version');
    }

    /**
     * @inheritDoc
     */
    public function setVersion(int $version): DocumentInterface
    {
        return $this->setData('version', $version);
    }

    /**
     * @inheritDoc
     */
    public function isCurrent(): bool
    {
        return (bool)$this->getData('is_current');
    }

    /**
     * @inheritDoc
     */
    public function setCurrent(bool $current): DocumentInterface
    {
        return $this->setData('is_current', $current);
    }

    /**
     * @inheritDoc
     */
    public function getChecksum(): string
    {
        return (string)$this->getData('checksum');
    }

    /**
     * @inheritDoc
     */
    public function setChecksum(string $checksum): DocumentInterface
    {
        return $this->setData('checksum', $checksum);
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
