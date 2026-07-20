<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Block\Adminhtml\Approval\View;

use Magento\Ui\Component\Control\ButtonProviderInterface;

/**
 * "Approve" button for the Order Approval detail/decision view.
 *
 * Reuses the same "save" client event as a regular Ui/Component form Save button (see
 * Block\Adminhtml\GroupConfig\Edit\SaveButton), but overrides the target submit URL via the
 * button's own "options.submit_url" (the same mechanism core Magento uses for
 * Save / Save & Close / Save & Duplicate buttons on one form, e.g.
 * Magento\Catalog\Block\Adminhtml\Product\Edit\Button\SaveButton). This lets one shared form
 * (order_approval_form.xml) post its current field values -- most importantly the
 * "decision_notes" textarea -- to three different controller actions (approve/reject/retry)
 * depending on which button was clicked, without needing three separate forms.
 */
class ApproveButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @inheritDoc
     */
    public function getButtonData(): array
    {
        // Per the Design doc / OrderApprovalActions' grid wiring, Approve is only offered while
        // the approval is still pending manual verification.
        if (!$this->isPending()) {
            return [];
        }

        return [
            'label' => __('Approve'),
            'class' => 'action-primary',
            'data_attribute' => [
                'mage-init' => ['button' => ['event' => 'save']],
                'form-role' => 'save',
            ],
            'options' => [
                'submit_url' => $this->getUrl(
                    'customercompliance/approval/approve',
                    ['approval_id' => $this->getApprovalId()]
                ),
            ],
            'sort_order' => 40,
        ];
    }
}
