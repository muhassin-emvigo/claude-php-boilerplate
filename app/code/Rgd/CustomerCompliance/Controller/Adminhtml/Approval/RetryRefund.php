<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Controller\Adminhtml\Approval;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\State\StateException;
use Throwable;
use Rgd\CustomerCompliance\Api\OrderApprovalServiceInterface;

/**
 * Retries a previously failed refund for a rejected order approval.
 */
class RetryRefund extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Rgd_CustomerCompliance::reject';

    /**
     * @param Context $context
     * @param OrderApprovalServiceInterface $orderApprovalService
     */
    public function __construct(
        Context $context,
        private readonly OrderApprovalServiceInterface $orderApprovalService
    ) {
        parent::__construct($context);
    }

    /**
     * Retry a previously failed refund for a rejected order.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $approvalId = (int)$this->getRequest()->getParam('approval_id');

        if (!$approvalId) {
            $this->messageManager->addErrorMessage(__('An approval id is required.'));
            return $resultRedirect->setPath('*/*/index');
        }

        try {
            $this->orderApprovalService->retryRefund($approvalId);
            $this->messageManager->addSuccessMessage(__('The refund retry has been triggered.'));
        } catch (StateException $e) {
            $this->messageManager->addErrorMessage(
                __('This approval is not in a state that allows a refund retry.')
            );
        } catch (Throwable $e) {
            $this->messageManager->addErrorMessage(
                __('Something went wrong while retrying the refund: %1', $e->getMessage())
            );
        }

        return $resultRedirect->setPath('*/*/view', ['approval_id' => $approvalId]);
    }
}
