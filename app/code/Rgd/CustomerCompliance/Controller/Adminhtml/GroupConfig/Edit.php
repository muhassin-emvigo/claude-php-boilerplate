<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Controller\Adminhtml\GroupConfig;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page;
use Rgd\CustomerCompliance\Api\GroupConfigRepositoryInterface;

/**
 * Group Config edit (and new) form page.
 */
class Edit extends Action
{
    public const ADMIN_RESOURCE = 'Rgd_CustomerCompliance::group_config';

    /**
     * @param Context $context
     * @param Registry $registry
     * @param GroupConfigRepositoryInterface $groupConfigRepository
     */
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly GroupConfigRepositoryInterface $groupConfigRepository
    ) {
        parent::__construct($context);
    }

    /**
     * Render the customer group compliance configuration create/edit form.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $configId = (int)$this->getRequest()->getParam('config_id');
        $groupConfig = null;

        if ($configId) {
            try {
                $groupConfig = $this->groupConfigRepository->getByCustomerGroupId($configId);
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage(__('This group config no longer exists.'));

                /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
                $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
                return $resultRedirect->setPath('*/*/index');
            }
        }

        $this->registry->register('rgd_customercompliance_group_config', $groupConfig);

        /** @var Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Rgd_CustomerCompliance::group_config');
        $resultPage->addBreadcrumb(__('Customer Compliance'), __('Customer Compliance'));
        $resultPage->addBreadcrumb(__('Group Configs'), __('Group Configs'));
        $resultPage->getConfig()->getTitle()->prepend(
            $configId ? __('Edit Group Config') : __('New Group Config')
        );

        return $resultPage;
    }
}
