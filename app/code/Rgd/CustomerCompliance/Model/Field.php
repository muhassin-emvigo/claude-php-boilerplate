<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model;

use Magento\Framework\Model\AbstractModel;
use Rgd\CustomerCompliance\Api\Data\FieldInterface;
use Rgd\CustomerCompliance\Model\ResourceModel\Field as FieldResourceModel;

/**
 * Compliance field definition model.
 */
class Field extends AbstractModel implements FieldInterface
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(FieldResourceModel::class);
    }

    /**
     * @inheritDoc
     */
    public function getFieldId(): ?int
    {
        $value = $this->getData('field_id');

        return $value !== null ? (int)$value : null;
    }

    /**
     * @inheritDoc
     */
    public function setFieldId(?int $fieldId): FieldInterface
    {
        return $this->setData('field_id', $fieldId);
    }

    /**
     * @inheritDoc
     */
    public function getConfigId(): int
    {
        return (int)$this->getData('config_id');
    }

    /**
     * @inheritDoc
     */
    public function setConfigId(int $configId): FieldInterface
    {
        return $this->setData('config_id', $configId);
    }

    /**
     * @inheritDoc
     */
    public function getFieldCode(): string
    {
        return (string)$this->getData('field_code');
    }

    /**
     * @inheritDoc
     */
    public function setFieldCode(string $fieldCode): FieldInterface
    {
        return $this->setData('field_code', $fieldCode);
    }

    /**
     * @inheritDoc
     */
    public function getFieldType(): string
    {
        return (string)$this->getData('field_type');
    }

    /**
     * @inheritDoc
     */
    public function setFieldType(string $fieldType): FieldInterface
    {
        return $this->setData('field_type', $fieldType);
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return (string)$this->getData('label');
    }

    /**
     * @inheritDoc
     */
    public function setLabel(string $label): FieldInterface
    {
        return $this->setData('label', $label);
    }

    /**
     * @inheritDoc
     */
    public function isRequired(): bool
    {
        return (bool)$this->getData('is_required');
    }

    /**
     * @inheritDoc
     */
    public function setRequired(bool $required): FieldInterface
    {
        return $this->setData('is_required', $required);
    }

    /**
     * @inheritDoc
     */
    public function getSortOrder(): int
    {
        return (int)$this->getData('sort_order');
    }

    /**
     * @inheritDoc
     */
    public function setSortOrder(int $sortOrder): FieldInterface
    {
        return $this->setData('sort_order', $sortOrder);
    }

    /**
     * @inheritDoc
     */
    public function getOptionsJson(): ?string
    {
        $value = $this->getData('options_json');

        return $value !== null ? (string)$value : null;
    }

    /**
     * @inheritDoc
     */
    public function setOptionsJson(?string $optionsJson): FieldInterface
    {
        return $this->setData('options_json', $optionsJson);
    }

    /**
     * @inheritDoc
     */
    public function getOptions(): ?array
    {
        $value = $this->getData('options_json');

        if ($value === null) {
            return null;
        }

        $decoded = json_decode((string)$value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @inheritDoc
     */
    public function setOptions(?array $options): FieldInterface
    {
        return $this->setData('options_json', $options !== null ? json_encode($options) : null);
    }

    /**
     * @inheritDoc
     */
    public function getAllowedExtensions(): ?string
    {
        $value = $this->getData('allowed_extensions');

        return $value !== null ? (string)$value : null;
    }

    /**
     * @inheritDoc
     */
    public function setAllowedExtensions(?string $allowedExtensions): FieldInterface
    {
        return $this->setData('allowed_extensions', $allowedExtensions);
    }

    /**
     * @inheritDoc
     */
    public function getMaxFileSizeKb(): ?int
    {
        $value = $this->getData('max_file_size_kb');

        return $value !== null ? (int)$value : null;
    }

    /**
     * @inheritDoc
     */
    public function setMaxFileSizeKb(?int $maxFileSizeKb): FieldInterface
    {
        return $this->setData('max_file_size_kb', $maxFileSizeKb);
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
    public function setVersion(int $version): FieldInterface
    {
        return $this->setData('version', $version);
    }

    /**
     * @inheritDoc
     */
    public function isActive(): bool
    {
        return (bool)$this->getData('is_active');
    }

    /**
     * @inheritDoc
     */
    public function setActive(bool $active): FieldInterface
    {
        return $this->setData('is_active', $active);
    }
}
