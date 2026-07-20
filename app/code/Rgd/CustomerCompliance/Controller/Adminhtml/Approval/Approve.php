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
 * Approves a pending order compliance verification.
 */
class Approve extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Rgd_CustomerCompliance::approve';

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
     * Approve the pending order held for compliance review.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $approvalId = (int)$this->getRequest()->getParam('approval_id');
        $notes = $this->getRequest()->getParam('notes');
        $notes = $notes !== null && $notes !== '' ? (string)$notes : null;

        if (!$approvalId) {
            $this->messageManager->addErrorMessage(__('An approval id is required.'));
            return $resultRedirect->setPath('*/*/index');
        }

        $adminUserId = (int)$this->_session->getUser()->getId();

        try {
            $this->orderApprovalService->approve($approvalId, $adminUserId, $notes);
            $this->messageManager->addSuccessMessage(__('The order approval has been approved.'));
        } catch (StateException $e) {
            $this->messageManager->addErrorMessage(
                __('This approval has already been processed and cannot be approved again.')
            );
        } catch (Throwable $e) {
            $this->messageManager->addErrorMessage(
                __('Something went wrong while approving this order: %1', $e->getMessage())
            );
        }

        return $resultRedirect->setPath('*/*/view', ['approval_id' => $approvalId]);
    }
}
