<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Api\Data;

/**
 * A customer group that is eligible for compliance configuration.
 *
 * @api
 */
interface EligibleGroupInterface
{
    /**
     * Get the customer group id.
     *
     * @return int
     */
    public function getCustomerGroupId(): int;

    /**
     * Set the customer group id.
     *
     * @param int $customerGroupId
     * @return $this
     */
    public function setCustomerGroupId(int $customerGroupId): self;

    /**
     * Get the customer group label.
     *
     * @return string
     */
    public function getGroupLabel(): string;

    /**
     * Set the customer group label.
     *
     * @param string $groupLabel
     * @return $this
     */
    public function setGroupLabel(string $groupLabel): self;

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
}
