<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Controller\Adminhtml\GroupConfig;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Throwable;
use Rgd\CustomerCompliance\Api\GroupConfigRepositoryInterface;

/**
 * Deletes a Group Config.
 */
class Delete extends Action
{
    public const ADMIN_RESOURCE = 'Rgd_CustomerCompliance::group_config';

    /**
     * @param Context $context
     * @param GroupConfigRepositoryInterface $groupConfigRepository
     */
    public function __construct(
        Context $context,
        private readonly GroupConfigRepositoryInterface $groupConfigRepository
    ) {
        parent::__construct($context);
    }

    /**
     * Delete a customer group's compliance configuration.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $configId = (int)$this->getRequest()->getParam('config_id');

        if (!$configId) {
            $this->messageManager->addErrorMessage(__('A group config id is required.'));
            return $resultRedirect->setPath('*/*/index');
        }

        try {
            $groupConfig = $this->groupConfigRepository->getByCustomerGroupId($configId);
            $this->groupConfigRepository->delete($groupConfig);
            $this->messageManager->addSuccessMessage(__('The group config has been deleted.'));
        } catch (Throwable $e) {
            $this->messageManager->addErrorMessage(
                __('Something went wrong while deleting the group config: %1', $e->getMessage())
            );
        }

        return $resultRedirect->setPath('*/*/index');
    }
}
