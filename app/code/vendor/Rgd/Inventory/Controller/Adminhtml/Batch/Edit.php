<?php
declare(strict_types=1);

namespace Rgd\Inventory\Controller\Adminhtml\Batch;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;
use Rgd\Inventory\Api\BatchRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Batch create/edit controller
 */
class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Rgd_Inventory::batch_manage';

    public function __construct(
        Context $context,
        private PageFactory $pageFactory,
        private BatchRepositoryInterface $batchRepository,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $batchId = (int)$this->getRequest()->getParam('batch_id');
        $batch = null;

        if ($batchId) {
            try {
                $batch = $this->batchRepository->getById($batchId);
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage((string)__('The batch does not exist.'));
                return $this->resultRedirectFactory->create()->setPath('rgd_inventory/batch/index');
            }
        }

        $resultPage = $this->pageFactory->create();
        $title = $batch ? __('Edit Batch %1', $batch->getBatchNumber()) : __('New Batch');
        $resultPage->getConfig()->getTitle()->prepend((string)$title);

        return $resultPage;
    }
}
