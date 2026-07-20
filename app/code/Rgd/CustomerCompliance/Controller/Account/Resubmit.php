<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Controller\Account;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\Page;
use Rgd\CustomerCompliance\Api\Data\OrderApprovalInterface;
use Rgd\CustomerCompliance\Api\OrderApprovalRepositoryInterface;

/**
 * Shows the document resubmission form for a rejected order.
 *
 * Guards: the customer must be logged in, an approval record must exist for the order, and its
 * status must be STATUS_REJECTED; any other case redirects away with a message rather than
 * rendering the form.
 */
class Resubmit extends Action implements HttpGetActionInterface
{
    /**
     * @param Context $context
     * @param CustomerSession $customerSession
     * @param OrderApprovalRepositoryInterface $orderApprovalRepository
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        Context $context,
        private readonly CustomerSession $customerSession,
        private readonly OrderApprovalRepositoryInterface $orderApprovalRepository,
        private readonly ManagerInterface $messageManager
    ) {
        parent::__construct($context);
    }

    /**
     * Render the document resubmission form, redirecting guests to login.
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            /** @var Redirect $redirect */
            $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $redirect->setPath('customer/account/login');
        }

        $orderId = (int)$this->getRequest()->getParam('order_id');

        try {
            $approval = $this->orderApprovalRepository->getByOrderId($orderId);
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('That order does not have a compliance review on file.'));

            /** @var Redirect $redirect */
            $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $redirect->setPath('customer/account/');
        }

        if ((int)$approval->getCustomerId() !== (int)$this->customerSession->getCustomerId()) {
            $this->messageManager->addErrorMessage(__('That order does not have a compliance review on file.'));

            /** @var Redirect $redirect */
            $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $redirect->setPath('customer/account/');
        }

        if ($approval->getStatus() !== OrderApprovalInterface::STATUS_REJECTED) {
            $this->messageManager->addNoticeMessage(__('This order does not require document resubmission.'));

            /** @var Redirect $redirect */
            $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $redirect->setPath('sales/order/view', ['order_id' => $orderId]);
        }

        /** @var Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Magento_Customer::customer_account');

        return $resultPage;
    }
}
