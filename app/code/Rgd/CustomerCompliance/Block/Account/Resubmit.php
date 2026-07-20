<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Block\Account;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Rgd\CustomerCompliance\Api\Data\FieldInterface;
use Rgd\CustomerCompliance\Api\Data\OrderApprovalInterface;
use Rgd\CustomerCompliance\Api\FieldConfigProviderInterface;
use Rgd\CustomerCompliance\Api\OrderApprovalRepositoryInterface;

/**
 * Exposes the rejected order's decision notes and the customer's compliance field set to the
 * `account/resubmit.phtml` template. The group of fields shown is the fixed set configured for
 * the customer's current group; unlike the registration page, there is no group switcher here.
 */
class Resubmit extends Template
{
    /**
     * @param Context $context
     * @param OrderApprovalRepositoryInterface $orderApprovalRepository
     * @param FieldConfigProviderInterface $fieldConfigProvider
     * @param CustomerSession $customerSession
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly OrderApprovalRepositoryInterface $orderApprovalRepository,
        private readonly FieldConfigProviderInterface $fieldConfigProvider,
        private readonly CustomerSession $customerSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get the order id being resubmitted against, from the `order_id` request param.
     *
     * @return int
     */
    public function getOrderId(): int
    {
        return (int)$this->getRequest()->getParam('order_id');
    }

    /**
     * Get the approval record for the current order, or null if none exists.
     *
     * @return OrderApprovalInterface|null
     */
    public function getApproval(): ?OrderApprovalInterface
    {
        $orderId = $this->getOrderId();
        if ($orderId <= 0) {
            return null;
        }

        try {
            return $this->orderApprovalRepository->getByOrderId($orderId);
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * Get the reviewer's decision notes for the rejected order, if any.
     *
     * @return string
     */
    public function getDecisionNotes(): string
    {
        $approval = $this->getApproval();

        return $approval !== null ? (string)$approval->getDecisionNotes() : '';
    }

    /**
     * Get the compliance fields to render for resubmission (the customer's current group).
     *
     * @return FieldInterface[]
     */
    public function getFields(): array
    {
        $customerGroupId = (int)$this->customerSession->getCustomerGroupId();
        $fields = $this->fieldConfigProvider->getFieldsForGroup($customerGroupId);

        usort(
            $fields,
            static fn (FieldInterface $a, FieldInterface $b): int => $a->getSortOrder() <=> $b->getSortOrder()
        );

        return $fields;
    }

    /**
     * Build the form action URL for the resubmission POST.
     *
     * @return string
     */
    public function getFormActionUrl(): string
    {
        return $this->getUrl('customercompliance/account/resubmitpost', ['order_id' => $this->getOrderId()]);
    }
}
