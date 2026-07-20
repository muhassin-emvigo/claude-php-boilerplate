<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Api\Data;

/**
 * Compliance configuration for a single customer group.
 *
 * @api
 */
interface GroupConfigInterface
{
    /**
     * Get the config id.
     *
     * @return int|null
     */
    public function getConfigId(): ?int;

    /**
     * Set the config id.
     *
     * @param int|null $configId
     * @return $this
     */
    public function setConfigId(?int $configId): self;

    /**
     * Get the customer group id this config applies to.
     *
     * @return int
     */
    public function getCustomerGroupId(): int;

    /**
     * Set the customer group id this config applies to.
     *
     * @param int $customerGroupId
     * @return $this
     */
    public function setCustomerGroupId(int $customerGroupId): self;

    /**
     * Whether order approval is required for this customer group.
     *
     * @return bool
     */
    public function isApprovalRequired(): bool;

    /**
     * Set whether order approval is required for this customer group.
     *
     * @param bool $approvalRequired
     * @return $this
     */
    public function setApprovalRequired(bool $approvalRequired): self;

    /**
     * Whether registration compliance fields are enabled for this customer group.
     *
     * @return bool
     */
    public function isRegistrationFieldsEnabled(): bool;

    /**
     * Set whether registration compliance fields are enabled for this customer group.
     *
     * @param bool $registrationFieldsEnabled
     * @return $this
     */
    public function setRegistrationFieldsEnabled(bool $registrationFieldsEnabled): self;

    /**
     * Get the creation timestamp.
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * Get the last-updated timestamp.
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string;
}
