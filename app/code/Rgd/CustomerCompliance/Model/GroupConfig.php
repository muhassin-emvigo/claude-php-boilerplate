<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model;

use Magento\Framework\Model\AbstractModel;
use Rgd\CustomerCompliance\Api\Data\GroupConfigInterface;
use Rgd\CustomerCompliance\Model\ResourceModel\GroupConfig as GroupConfigResourceModel;

/**
 * Customer-group compliance configuration model.
 */
class GroupConfig extends AbstractModel implements GroupConfigInterface
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(GroupConfigResourceModel::class);
    }

    /**
     * @inheritDoc
     */
    public function getConfigId(): ?int
    {
        $value = $this->getData('config_id');

        return $value !== null ? (int)$value : null;
    }

    /**
     * @inheritDoc
     */
    public function setConfigId(?int $configId): GroupConfigInterface
    {
        return $this->setData('config_id', $configId);
    }

    /**
     * @inheritDoc
     */
    public function getCustomerGroupId(): int
    {
        return (int)$this->getData('customer_group_id');
    }

    /**
     * @inheritDoc
     */
    public function setCustomerGroupId(int $customerGroupId): GroupConfigInterface
    {
        return $this->setData('customer_group_id', $customerGroupId);
    }

    /**
     * @inheritDoc
     */
    public function isApprovalRequired(): bool
    {
        return (bool)$this->getData('is_approval_required');
    }

    /**
     * @inheritDoc
     */
    public function setApprovalRequired(bool $approvalRequired): GroupConfigInterface
    {
        return $this->setData('is_approval_required', $approvalRequired);
    }

    /**
     * @inheritDoc
     */
    public function isRegistrationFieldsEnabled(): bool
    {
        return (bool)$this->getData('is_registration_fields_enabled');
    }

    /**
     * @inheritDoc
     */
    public function setRegistrationFieldsEnabled(bool $registrationFieldsEnabled): GroupConfigInterface
    {
        return $this->setData('is_registration_fields_enabled', $registrationFieldsEnabled);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): ?string
    {
        $value = $this->getData('created_at');

        return $value !== null ? (string)$value : null;
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt(): ?string
    {
        $value = $this->getData('updated_at');

        return $value !== null ? (string)$value : null;
    }
}
