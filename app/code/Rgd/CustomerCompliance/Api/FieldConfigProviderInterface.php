<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Api;

/**
 * Provides resolved, active compliance field configuration for customer groups.
 *
 * @api
 */
interface FieldConfigProviderInterface
{
    /**
     * Get the active, ordered list of compliance fields configured for a customer group.
     *
     * @param int $customerGroupId
     * @return \Rgd\CustomerCompliance\Api\Data\FieldInterface[]
     */
    public function getFieldsForGroup(int $customerGroupId): array;

    /**
     * Get the list of customer groups that are eligible for compliance configuration.
     *
     * @return \Rgd\CustomerCompliance\Api\Data\EligibleGroupInterface[]
     */
    public function getEligibleGroups(): array;
}
