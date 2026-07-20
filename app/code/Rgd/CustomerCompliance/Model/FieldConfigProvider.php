<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model;

use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Rgd\CustomerCompliance\Api\Data\EligibleGroupInterfaceFactory;
use Rgd\CustomerCompliance\Api\FieldConfigProviderInterface;
use Rgd\CustomerCompliance\Api\FieldRepositoryInterface;
use Rgd\CustomerCompliance\Api\GroupConfigRepositoryInterface;

/**
 * Provides resolved, active compliance field configuration for customer groups.
 */
class FieldConfigProvider implements FieldConfigProviderInterface
{
    /**
     * @param FieldRepositoryInterface $fieldRepository
     * @param GroupConfigRepositoryInterface $groupConfigRepository
     * @param GroupRepositoryInterface $customerGroupRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param EligibleGroupInterfaceFactory $eligibleGroupFactory
     */
    public function __construct(
        private readonly FieldRepositoryInterface $fieldRepository,
        private readonly GroupConfigRepositoryInterface $groupConfigRepository,
        private readonly GroupRepositoryInterface $customerGroupRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly EligibleGroupInterfaceFactory $eligibleGroupFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getFieldsForGroup(int $customerGroupId): array
    {
        try {
            $groupConfig = $this->groupConfigRepository->getByCustomerGroupId($customerGroupId);
        } catch (NoSuchEntityException $e) {
            // A customer group with no compliance configuration simply has no fields.
            return [];
        }

        return $this->fieldRepository->getListByConfigId((int)$groupConfig->getConfigId())->getItems();
    }

    /**
     * @inheritDoc
     */
    public function getEligibleGroups(): array
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('is_registration_fields_enabled', 1)
            ->create();

        $groupConfigs = $this->groupConfigRepository->getList($searchCriteria)->getItems();

        $eligibleGroups = [];

        foreach ($groupConfigs as $groupConfig) {
            try {
                $customerGroup = $this->customerGroupRepository->getById($groupConfig->getCustomerGroupId());
            } catch (NoSuchEntityException $e) {
                // The group config references a customer group that has since been deleted;
                // skip it rather than failing the whole list.
                continue;
            }

            /** @var \Rgd\CustomerCompliance\Api\Data\EligibleGroupInterface $eligibleGroup */
            $eligibleGroup = $this->eligibleGroupFactory->create();
            $eligibleGroup->setCustomerGroupId($groupConfig->getCustomerGroupId())
                ->setGroupLabel($customerGroup->getCode())
                ->setApprovalRequired($groupConfig->isApprovalRequired());

            $eligibleGroups[] = $eligibleGroup;
        }

        return $eligibleGroups;
    }
}
