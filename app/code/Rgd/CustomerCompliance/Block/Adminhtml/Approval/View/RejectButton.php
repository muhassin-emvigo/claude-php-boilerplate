<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Block\Adminhtml\Approval\View;

use Magento\Ui\Component\Control\ButtonProviderInterface;

/**
 * "Reject" button for the Order Approval detail/decision view.
 *
 * See ApproveButton's PHPDoc for the "save" event + per-button "options.submit_url" mechanism
 * this relies on. Controller\Adminhtml\Approval\Reject requires a non-empty "notes" request
 * param (trimmed server-side) -- the client-side "required-entry" validation on the
 * decision_notes field in order_approval_form.xml is a UX nicety only, the server-side check
 * is authoritative regardless of it, matching this module's established convention (see
 * group_config_form.xml's is_registration_fields_enabled field notice).
 */
class RejectButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @inheritDoc
     */
    public function getButtonData(): array
    {
        if (!$this->isPending()) {
            return [];
        }

        return [
            'label' => __('Reject'),
            'class' => 'action-secondary',
            'data_attribute' => [
                'mage-init' => ['button' => ['event' => 'save']],
                'form-role' => 'save',
            ],
            'options' => [
                'submit_url' => $this->getUrl(
                    'customercompliance/approval/reject',
                    ['approval_id' => $this->getApprovalId()]
                ),
            ],
            'sort_order' => 50,
        ];
    }
}
