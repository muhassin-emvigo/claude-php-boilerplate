<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Controller\Adminhtml\Field;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Throwable;
use Rgd\CustomerCompliance\Api\FieldRepositoryInterface;

/**
 * Deletes a Field.
 */
class Delete extends Action
{
    public const ADMIN_RESOURCE = 'Rgd_CustomerCompliance::field';

    /**
     * @param Context $context
     * @param FieldRepositoryInterface $fieldRepository
     */
    public function __construct(
        Context $context,
        private readonly FieldRepositoryInterface $fieldRepository
    ) {
        parent::__construct($context);
    }

    /**
     * Delete a compliance field definition.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $fieldId = (int)$this->getRequest()->getParam('field_id');
        $configId = (int)$this->getRequest()->getParam('config_id');

        if (!$fieldId) {
            $this->messageManager->addErrorMessage(__('A field id is required.'));
            return $resultRedirect->setPath('*/*/index', ['config_id' => $configId]);
        }

        try {
            $field = $this->fieldRepository->getById($fieldId);
            $this->fieldRepository->delete($field);
            $this->messageManager->addSuccessMessage(__('The field has been deleted.'));
        } catch (Throwable $e) {
            $this->messageManager->addErrorMessage(
                __('Something went wrong while deleting the field: %1', $e->getMessage())
            );
        }

        return $resultRedirect->setPath('*/*/index', ['config_id' => $configId]);
    }
}
