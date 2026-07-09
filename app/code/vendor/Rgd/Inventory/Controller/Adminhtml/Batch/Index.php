<?php
declare(strict_types=1);

namespace Rgd\Inventory\Controller\Adminhtml\Batch;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * Batch listing controller
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Rgd_Inventory::batch';

    public function __construct(
        Context $context,
        private PageFactory $pageFactory,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->pageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend((string)__('Batch Inventory'));

        return $resultPage;
    }
}
