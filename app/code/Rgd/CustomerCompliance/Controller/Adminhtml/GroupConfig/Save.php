<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Controller\Adminhtml\GroupConfig;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Throwable;
use Rgd\CustomerCompliance\Api\Data\GroupConfigInterfaceFactory;
use Rgd\CustomerCompliance\Api\GroupConfigRepositoryInterface;

/**
 * Persists a Group Config from the admin edit form.
 */
class Save extends Action
{
    public const ADMIN_RESOURCE = 'Rgd_CustomerCompliance::group_config';

    /**
     * @param Context $context
     * @param GroupConfigRepositoryInterface $groupConfigRepository
     * @param GroupConfigInterfaceFactory $groupConfigFactory
     */
    public function __construct(
        Context $context,
        private readonly GroupConfigRepositoryInterface $groupConfigRepository,
        private readonly GroupConfigInterfaceFactory $groupConfigFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Save a customer group's compliance configuration.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $data = $this->getRequest()->getParams();
        $customerGroupId = (int)($data['customer_group_id'] ?? 0);

        if (!$customerGroupId) {
            $this->messageManager->addErrorMessage(__('A customer group is required.'));
            return $resultRedirect->setPath('*/*/index');
        }

        try {
            try {
                $groupConfig = $this->groupConfigRepository->getByCustomerGroupId($customerGroupId);
            } catch (NoSuchEntityException $e) {
                $groupConfig = $this->groupConfigFactory->create();
                $groupConfig->setCustomerGroupId($customerGroupId);
            }

            $isApprovalRequired = !empty($data['is_approval_required']);
            $isRegistrationFieldsEnabled = !empty($data['is_registration_fields_enabled']);

            // Stakeholder-confirmed business rule: approval-not-required implies registration
            // fields must also be disabled, regardless of what was submitted in the form.
            if (!$isApprovalRequired) {
                $isRegistrationFieldsEnabled = false;
            }

            $groupConfig->setIsApprovalRequired($isApprovalRequired);
            $groupConfig->setIsRegistrationFieldsEnabled($isRegistrationFieldsEnabled);

            $this->groupConfigRepository->save($groupConfig);

            $this->messageManager->addSuccessMessage(__('The group config has been saved.'));

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath(
                    '*/*/edit',
                    ['config_id' => $customerGroupId]
                );
            }

            return $resultRedirect->setPath('*/*/index');
        } catch (Throwable $e) {
            $this->messageManager->addErrorMessage(
                __('Something went wrong while saving the group config: %1', $e->getMessage())
            );

            return $resultRedirect->setPath('*/*/edit', ['config_id' => $customerGroupId]);
        }
    }
}
