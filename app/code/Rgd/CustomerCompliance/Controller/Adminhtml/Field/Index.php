<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Controller\Adminhtml\Field;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;

/**
 * Fields grid listing page (scoped to a Group Config via the "config_id" param, rendered as a
 * tab on the Group Config edit page per the Design doc).
 */
class Index extends Action
{
    public const ADMIN_RESOURCE = 'Rgd_CustomerCompliance::field';

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
        $resultPage->addBreadcrumb(__('Fields'), __('Fields'));
        $resultPage->getConfig()->getTitle()->prepend(__('Fields'));

        return $resultPage;
    }
}
