<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Controller\Account;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Result\Page;

/**
 * Renders the "My Compliance Documents" page under My Account.
 *
 * Document loading itself is delegated to {@see \Rgd\CustomerCompliance\Block\Account\Documents},
 * which injects the repository directly; this controller's only job is the login guard and
 * handing off to the page result.
 */
class Documents extends Action implements HttpGetActionInterface
{
    /**
     * @param Context $context
     * @param CustomerSession $customerSession
     */
    public function __construct(
        Context $context,
        private readonly CustomerSession $customerSession
    ) {
        parent::__construct($context);
    }

    /**
     * Render the compliance documents page, redirecting guests to login.
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

        /** @var Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Magento_Customer::customer_account');

        return $resultPage;
    }
}
