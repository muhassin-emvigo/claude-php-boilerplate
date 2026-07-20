<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Controller\Adminhtml\GroupConfig;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;

/**
 * Group Configs grid listing page.
 */
class Index extends Action
{
    public const ADMIN_RESOURCE = 'Rgd_CustomerCompliance::group_config';

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
        $resultPage->setActiveMenu('Rgd_CustomerCompliance::group_config');
        $resultPage->addBreadcrumb(__('Customer Compliance'), __('Customer Compliance'));
        $resultPage->addBreadcrumb(__('Group Configs'), __('Group Configs'));
        $resultPage->getConfig()->getTitle()->prepend(__('Group Configs'));

        return $resultPage;
    }
}
