<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Api\Data;

/**
 * A single submitted compliance field value for a customer.
 *
 * @api
 */
interface FieldValueInterface
{
    /**
     * Get the value id.
     *
     * @return int|null
     */
    public function getValueId(): ?int;

    /**
     * Set the value id.
     *
     * @param int|null $valueId
     * @return $this
     */
    public function setValueId(?int $valueId): self;

    /**
     * Get the customer id.
     *
     * @return int
     */
    public function getCustomerId(): int;

    /**
     * Set the customer id.
     *
     * @param int $customerId
     * @return $this
     */
    public function setCustomerId(int $customerId): self;

    /**
     * Get the field id this value belongs to.
     *
     * @return int
     */
    public function getFieldId(): int;

    /**
     * Set the field id this value belongs to.
     *
     * @param int $fieldId
     * @return $this
     */
    public function setFieldId(int $fieldId): self;

    /**
     * Get the submitted scalar value.
     *
     * @return string|null
     */
    public function getValue(): ?string;

    /**
     * Set the submitted scalar value.
     *
     * @param string|null $value
     * @return $this
     */
    public function setValue(?string $value): self;

    /**
     * Get the id of the associated document, if this value is a file-type field.
     *
     * @return int|null
     */
    public function getDocumentId(): ?int;

    /**
     * Set the id of the associated document, if this value is a file-type field.
     *
     * @param int|null $documentId
     * @return $this
     */
    public function setDocumentId(?int $documentId): self;
}
