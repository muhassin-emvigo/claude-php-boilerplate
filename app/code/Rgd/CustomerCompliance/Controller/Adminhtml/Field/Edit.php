<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Controller\Adminhtml\Field;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page;
use Rgd\CustomerCompliance\Api\FieldRepositoryInterface;

/**
 * Field edit (and new) form. This grid is scoped to a group config: new fields require a
 * "config_id" param identifying the owning Group Config, per the Design doc's decision to
 * surface field management as a tab on the Group Config edit page.
 */
class Edit extends Action
{
    public const ADMIN_RESOURCE = 'Rgd_CustomerCompliance::field';

    /**
     * @param Context $context
     * @param Registry $registry
     * @param FieldRepositoryInterface $fieldRepository
     */
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly FieldRepositoryInterface $fieldRepository
    ) {
        parent::__construct($context);
    }

    /**
     * Render the compliance field create/edit form.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $fieldId = (int)$this->getRequest()->getParam('field_id');
        $configId = (int)$this->getRequest()->getParam('config_id');

        if (!$fieldId && !$configId) {
            $this->messageManager->addErrorMessage(
                __('A group config id is required to create a new field.')
            );

            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $resultRedirect->setPath('customercompliance/groupconfig/index');
        }

        $field = null;
        if ($fieldId) {
            try {
                $field = $this->fieldRepository->getById($fieldId);
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage(__('This field no longer exists.'));

                $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
                return $resultRedirect->setPath('customercompliance/groupconfig/index');
            }
        }

        $this->registry->register('rgd_customercompliance_field', $field);
        $this->registry->register('rgd_customercompliance_field_config_id', $configId);

        /** @var Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Rgd_CustomerCompliance::group_config');
        $resultPage->addBreadcrumb(__('Customer Compliance'), __('Customer Compliance'));
        $resultPage->addBreadcrumb(__('Fields'), __('Fields'));
        $resultPage->getConfig()->getTitle()->prepend($fieldId ? __('Edit Field') : __('New Field'));

        return $resultPage;
    }
}
