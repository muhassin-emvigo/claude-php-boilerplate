<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model;

use Magento\Framework\Model\AbstractModel;
use Rgd\CustomerCompliance\Api\Data\FieldValueInterface;
use Rgd\CustomerCompliance\Model\ResourceModel\FieldValue as FieldValueResourceModel;

/**
 * Customer compliance field value model.
 */
class FieldValue extends AbstractModel implements FieldValueInterface
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(FieldValueResourceModel::class);
    }

    /**
     * @inheritDoc
     */
    public function getValueId(): ?int
    {
        $value = $this->getData('value_id');

        return $value !== null ? (int)$value : null;
    }

    /**
     * @inheritDoc
     */
    public function setValueId(?int $valueId): FieldValueInterface
    {
        return $this->setData('value_id', $valueId);
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
    public function setCustomerId(int $customerId): FieldValueInterface
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
    public function setFieldId(int $fieldId): FieldValueInterface
    {
        return $this->setData('field_id', $fieldId);
    }

    /**
     * @inheritDoc
     */
    public function getValue(): ?string
    {
        $value = $this->getData('value');

        return $value !== null ? (string)$value : null;
    }

    /**
     * @inheritDoc
     */
    public function setValue(?string $value): FieldValueInterface
    {
        return $this->setData('value', $value);
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
    public function setDocumentId(?int $documentId): FieldValueInterface
    {
        return $this->setData('document_id', $documentId);
    }
}
