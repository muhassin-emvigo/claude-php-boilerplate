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
 * Rejects a pending order compliance verification. Notes are required, matching the Eng spec's
 * 422-equivalent behavior at the service layer: enforced here at the UI layer too, so a
 * whitespace-only/missing note never reaches the service at all.
 */
class Reject extends Action implements HttpPostActionInterface
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
     * Reject the pending order held for compliance review.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $approvalId = (int)$this->getRequest()->getParam('approval_id');
        $notes = trim((string)$this->getRequest()->getParam('notes', ''));

        if (!$approvalId) {
            $this->messageManager->addErrorMessage(__('An approval id is required.'));
            return $resultRedirect->setPath('*/*/index');
        }

        if ($notes === '') {
            $this->messageManager->addErrorMessage(
                __('A rejection reason is required in order to reject this order.')
            );
            return $resultRedirect->setPath('*/*/view', ['approval_id' => $approvalId]);
        }

        $adminUserId = (int)$this->_session->getUser()->getId();

        try {
            $this->orderApprovalService->reject($approvalId, $adminUserId, $notes);
            $this->messageManager->addSuccessMessage(__('The order approval has been rejected.'));
        } catch (StateException $e) {
            $this->messageManager->addErrorMessage(
                __('This approval has already been processed and cannot be rejected again.')
            );
        } catch (Throwable $e) {
            $this->messageManager->addErrorMessage(
                __('Something went wrong while rejecting this order: %1', $e->getMessage())
            );
        }

        return $resultRedirect->setPath('*/*/view', ['approval_id' => $approvalId]);
    }
}
