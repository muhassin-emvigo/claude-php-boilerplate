<?php
declare(strict_types=1);

namespace Rgd\Inventory\Controller\Adminhtml\Batch;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Rgd\Inventory\Api\BatchRepositoryInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Batch delete controller
 */
class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Rgd_Inventory::batch_manage';

    public function __construct(
        Context $context,
        private BatchRepositoryInterface $batchRepository,
    ) {
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\Controller\Result\Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $batchId = (int)$this->getRequest()->getPostValue('batch_id');

            if (!$batchId) {
                $this->messageManager->addErrorMessage((string)__('No batch ID provided.'));
                return $resultRedirect->setPath('rgd_inventory/batch/index');
            }

            $this->batchRepository->deleteById($batchId);
            $this->messageManager->addSuccessMessage((string)__('Batch deleted successfully.'));
        } catch (CouldNotDeleteException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage((string)__('The batch does not exist.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage((string)__('An error occurred while deleting the batch.'));
        }

        return $resultRedirect->setPath('rgd_inventory/batch/index');
    }
}
