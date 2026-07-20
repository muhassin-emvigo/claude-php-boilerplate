<?php

// NOTE: Written in parallel with Model\FieldConfigProvider. The constructor is reconstructed
// from the fixed FieldConfigProviderInterface contract plus the collaborators named in the Eng
// spec; getEligibleGroups() building Api\Data\EligibleGroupInterface instances almost certainly
// requires an additional EligibleGroupInterfaceFactory dependency not explicitly enumerated in
// the spec, so it has been added here as a best-effort guess. Re-run and adjust (constructor arg
// order/count) against the actual implementation during Build-stage integration.

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Test\Unit\Model;

use Magento\Customer\Api\Data\GroupInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rgd\CustomerCompliance\Api\Data\EligibleGroupInterface;
use Rgd\CustomerCompliance\Api\Data\EligibleGroupInterfaceFactory;
use Rgd\CustomerCompliance\Api\Data\FieldInterface;
use Rgd\CustomerCompliance\Api\Data\FieldSearchResultsInterface;
use Rgd\CustomerCompliance\Api\Data\GroupConfigInterface;
use Rgd\CustomerCompliance\Api\Data\GroupConfigSearchResultsInterface;
use Rgd\CustomerCompliance\Api\FieldRepositoryInterface;
use Rgd\CustomerCompliance\Api\GroupConfigRepositoryInterface;
use Rgd\CustomerCompliance\Model\FieldConfigProvider;

/**
 * @covers \Rgd\CustomerCompliance\Model\FieldConfigProvider
 */
class FieldConfigProviderTest extends TestCase
{
    private FieldRepositoryInterface&MockObject $fieldRepository;
    private GroupConfigRepositoryInterface&MockObject $groupConfigRepository;
    private GroupRepositoryInterface&MockObject $customerGroupRepository;
    private SearchCriteriaBuilder&MockObject $searchCriteriaBuilder;
    private EligibleGroupInterfaceFactory&MockObject $eligibleGroupFactory;

    protected function setUp(): void
    {
        $this->fieldRepository = $this->createMock(FieldRepositoryInterface::class);
        $this->groupConfigRepository = $this->createMock(GroupConfigRepositoryInterface::class);
        $this->customerGroupRepository = $this->createMock(GroupRepositoryInterface::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->eligibleGroupFactory = $this->createMock(EligibleGroupInterfaceFactory::class);

        $this->searchCriteriaBuilder->method('addFilter')->willReturnSelf();
        $this->searchCriteriaBuilder->method('create')
            ->willReturn($this->createMock(SearchCriteriaInterface::class));
    }

    private function createProvider(): FieldConfigProvider
    {
        return new FieldConfigProvider(
            $this->fieldRepository,
            $this->groupConfigRepository,
            $this->customerGroupRepository,
            $this->searchCriteriaBuilder,
            $this->eligibleGroupFactory
        );
    }

    public function testGetFieldsForGroupReturnsEmptyArrayWhenNoComplianceConfigExists(): void
    {
        $this->groupConfigRepository->method('getByCustomerGroupId')->with(4)
            ->willThrowException(new NoSuchEntityException(__('no config')));

        $this->fieldRepository->expects($this->never())->method('getListByConfigId');

        $result = $this->createProvider()->getFieldsForGroup(4);

        $this->assertSame([], $result);
    }

    public function testGetFieldsForGroupReturnsFieldsFromRepositoryWhenConfigExists(): void
    {
        $groupConfig = $this->createMock(GroupConfigInterface::class);
        $groupConfig->method('getConfigId')->willReturn(42);
        $this->groupConfigRepository->method('getByCustomerGroupId')->with(4)->willReturn($groupConfig);

        $field1 = $this->createMock(FieldInterface::class);
        $field2 = $this->createMock(FieldInterface::class);

        $searchResults = $this->createMock(FieldSearchResultsInterface::class);
        $searchResults->method('getItems')->willReturn([$field1, $field2]);

        $this->fieldRepository->expects($this->once())->method('getListByConfigId')->with(42)
            ->willReturn($searchResults);

        $result = $this->createProvider()->getFieldsForGroup(4);

        $this->assertSame([$field1, $field2], $result);
    }

    public function testGetEligibleGroupsSkipsGroupsWhoseCoreCustomerGroupNoLongerExists(): void
    {
        $groupConfig1 = $this->createMock(GroupConfigInterface::class);
        $groupConfig1->method('getCustomerGroupId')->willReturn(1);
        $groupConfig1->method('isApprovalRequired')->willReturn(true);

        $groupConfig2 = $this->createMock(GroupConfigInterface::class);
        $groupConfig2->method('getCustomerGroupId')->willReturn(2);
        $groupConfig2->method('isApprovalRequired')->willReturn(false);

        $groupConfig3 = $this->createMock(GroupConfigInterface::class);
        $groupConfig3->method('getCustomerGroupId')->willReturn(3);
        $groupConfig3->method('isApprovalRequired')->willReturn(true);

        $searchResults = $this->createMock(GroupConfigSearchResultsInterface::class);
        $searchResults->method('getItems')->willReturn([$groupConfig1, $groupConfig2, $groupConfig3]);
        $this->groupConfigRepository->method('getList')->willReturn($searchResults);

        $customerGroup1 = $this->createMock(GroupInterface::class);
        $customerGroup1->method('getCode')->willReturn('Wholesale');

        $customerGroup3 = $this->createMock(GroupInterface::class);
        $customerGroup3->method('getCode')->willReturn('VIP');

        $this->customerGroupRepository->method('getById')->willReturnCallback(
            function (int $groupId) use ($customerGroup1, $customerGroup3) {
                return match ($groupId) {
                    1 => $customerGroup1,
                    3 => $customerGroup3,
                    default => throw new NoSuchEntityException(__('group %1 gone', $groupId)),
                };
            }
        );

        $eligibleGroup1 = $this->createEligibleGroupDouble();
        $eligibleGroup3 = $this->createEligibleGroupDouble();
        $this->eligibleGroupFactory->method('create')->willReturnOnConsecutiveCalls(
            $eligibleGroup1,
            $eligibleGroup3
        );

        $result = $this->createProvider()->getEligibleGroups();

        // Group config #2 (the one whose linked customer group no longer exists) must be
        // skipped without failing the whole list; the other two must still be present.
        $this->assertCount(2, $result);
    }

    private function createEligibleGroupDouble(): EligibleGroupInterface&MockObject
    {
        $eligibleGroup = $this->createMock(EligibleGroupInterface::class);
        $eligibleGroup->method('setCustomerGroupId')->willReturnSelf();
        $eligibleGroup->method('setGroupLabel')->willReturnSelf();
        $eligibleGroup->method('setApprovalRequired')->willReturnSelf();

        return $eligibleGroup;
    }
}
