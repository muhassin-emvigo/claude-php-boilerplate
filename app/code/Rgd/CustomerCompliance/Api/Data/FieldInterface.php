<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Api\Data;

/**
 * A single configurable compliance field definition.
 *
 * @api
 */
interface FieldInterface
{
    /**
     * Get the field id.
     *
     * @return int|null
     */
    public function getFieldId(): ?int;

    /**
     * Set the field id.
     *
     * @param int|null $fieldId
     * @return $this
     */
    public function setFieldId(?int $fieldId): self;

    /**
     * Get the id of the group config this field belongs to.
     *
     * @return int
     */
    public function getConfigId(): int;

    /**
     * Set the id of the group config this field belongs to.
     *
     * @param int $configId
     * @return $this
     */
    public function setConfigId(int $configId): self;

    /**
     * Get the field code (unique machine name).
     *
     * @return string
     */
    public function getFieldCode(): string;

    /**
     * Set the field code (unique machine name).
     *
     * @param string $fieldCode
     * @return $this
     */
    public function setFieldCode(string $fieldCode): self;

    /**
     * Get the field type (e.g. text, select, file).
     *
     * @return string
     */
    public function getFieldType(): string;

    /**
     * Set the field type (e.g. text, select, file).
     *
     * @param string $fieldType
     * @return $this
     */
    public function setFieldType(string $fieldType): self;

    /**
     * Get the field's customer-facing label.
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * Set the field's customer-facing label.
     *
     * @param string $label
     * @return $this
     */
    public function setLabel(string $label): self;

    /**
     * Whether the field is required.
     *
     * @return bool
     */
    public function isRequired(): bool;

    /**
     * Set whether the field is required.
     *
     * @param bool $required
     * @return $this
     */
    public function setRequired(bool $required): self;

    /**
     * Get the field's sort order.
     *
     * @return int
     */
    public function getSortOrder(): int;

    /**
     * Set the field's sort order.
     *
     * @param int $sortOrder
     * @return $this
     */
    public function setSortOrder(int $sortOrder): self;

    /**
     * Get the raw JSON-encoded options string (e.g. select/multiselect option list).
     *
     * @return string|null
     */
    public function getOptionsJson(): ?string;

    /**
     * Set the raw JSON-encoded options string.
     *
     * @param string|null $optionsJson
     * @return $this
     */
    public function setOptionsJson(?string $optionsJson): self;

    /**
     * Get the decoded options array.
     *
     * Convenience accessor; implementations decode {@see getOptionsJson()} on read.
     *
     * @return array|null
     */
    public function getOptions(): ?array;

    /**
     * Set options from a decoded array.
     *
     * Convenience mutator; implementations encode to {@see setOptionsJson()} on write.
     *
     * @param array|null $options
     * @return $this
     */
    public function setOptions(?array $options): self;

    /**
     * Get the comma-separated list of allowed file extensions (for file-type fields).
     *
     * @return string|null
     */
    public function getAllowedExtensions(): ?string;

    /**
     * Set the comma-separated list of allowed file extensions (for file-type fields).
     *
     * @param string|null $allowedExtensions
     * @return $this
     */
    public function setAllowedExtensions(?string $allowedExtensions): self;

    /**
     * Get the maximum allowed file size, in kilobytes (for file-type fields).
     *
     * @return int|null
     */
    public function getMaxFileSizeKb(): ?int;

    /**
     * Set the maximum allowed file size, in kilobytes (for file-type fields).
     *
     * @param int|null $maxFileSizeKb
     * @return $this
     */
    public function setMaxFileSizeKb(?int $maxFileSizeKb): self;

    /**
     * Get the field definition version.
     *
     * @return int
     */
    public function getVersion(): int;

    /**
     * Set the field definition version.
     *
     * @param int $version
     * @return $this
     */
    public function setVersion(int $version): self;

    /**
     * Whether the field is active.
     *
     * @return bool
     */
    public function isActive(): bool;

    /**
     * Set whether the field is active.
     *
     * @param bool $active
     * @return $this
     */
    public function setActive(bool $active): self;
}
