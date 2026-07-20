<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Controller\Adminhtml\Audit;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;

/**
 * Read-only audit log listing page. There is no Save/Delete for this grid.
 */
class Index extends Action
{
    public const ADMIN_RESOURCE = 'Rgd_CustomerCompliance::audit';

    /**
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        /** @var Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Rgd_CustomerCompliance::audit');
        $resultPage->addBreadcrumb(__('Customer Compliance'), __('Customer Compliance'));
        $resultPage->addBreadcrumb(__('Audit Log'), __('Audit Log'));
        $resultPage->getConfig()->getTitle()->prepend(__('Audit Log'));

        return $resultPage;
    }
}
