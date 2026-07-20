<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Block\Adminhtml\Approval\View;

use Magento\Ui\Component\Control\ButtonProviderInterface;

/**
 * "Retry Refund" button for the Order Approval detail view.
 *
 * Unlike Approve/Reject this action doesn't need any of the form's field values (
 * Controller\Adminhtml\Approval\RetryRefund only reads "approval_id"), so instead of the
 * "save" event + submit_url mechanism used by ApproveButton/RejectButton, this uses the
 * classic Magento admin "confirm, then POST" helper (`deleteConfirm`, the same global JS
 * function used by e.g. Magento_Cms's Block/Edit/DeleteButton) since retrying a refund is a
 * consequential, non-idempotent action worth an explicit confirmation step.
 */
class RetryRefundButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @inheritDoc
     */
    public function getButtonData(): array
    {
        if (!$this->isRefundRetryable()) {
            return [];
        }

        return [
            'label' => __('Retry Refund'),
            'class' => 'action-secondary',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s')",
                __('Are you sure you want to retry the refund for this order?'),
                $this->getUrl('customercompliance/approval/retryrefund', ['approval_id' => $this->getApprovalId()])
            ),
            'sort_order' => 60,
        ];
    }
}
