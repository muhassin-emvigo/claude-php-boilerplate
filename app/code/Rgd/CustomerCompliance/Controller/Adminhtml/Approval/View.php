<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Controller\Adminhtml\Approval;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page;
use Magento\Sales\Api\OrderRepositoryInterface;
use Rgd\CustomerCompliance\Api\OrderApprovalRepositoryInterface;

/**
 * Order Approval detail view. Loads the approval record plus the linked order/customer via
 * injected repositories and registers them for the layout/block to render; this controller does
 * not assemble any HTML itself.
 */
class View extends Action
{
    public const ADMIN_RESOURCE = 'Rgd_CustomerCompliance::approvals';

    /**
     * @param Context $context
     * @param Registry $registry
     * @param OrderApprovalRepositoryInterface $orderApprovalRepository
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly OrderApprovalRepositoryInterface $orderApprovalRepository,
        private readonly OrderRepositoryInterface $orderRepository
    ) {
        parent::__construct($context);
    }

    /**
     * Render the approval detail page for a single held order.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $approvalId = (int)$this->getRequest()->getParam('approval_id');

        try {
            $approval = $this->orderApprovalRepository->getById($approvalId);
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('This approval no longer exists.'));

            /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $resultRedirect->setPath('*/*/index');
        }

        $order = null;
        try {
            $order = $this->orderRepository->get($approval->getOrderId());
        } catch (NoSuchEntityException $e) {
            // Order was deleted/unavailable; the block can still render the approval record
            // itself and simply omit order-specific details.
            unset($e);
        }

        $this->registry->register('rgd_customercompliance_approval', $approval);
        $this->registry->register('rgd_customercompliance_order', $order);

        /** @var Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Rgd_CustomerCompliance::approvals');
        $resultPage->addBreadcrumb(__('Customer Compliance'), __('Customer Compliance'));
        $resultPage->addBreadcrumb(__('Order Approvals'), __('Order Approvals'));
        $resultPage->getConfig()->getTitle()->prepend(__('Approval #%1', $approvalId));

        return $resultPage;
    }
}
