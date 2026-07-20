<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Block\Adminhtml\Approval\View;

use Magento\Ui\Component\Control\ButtonProviderInterface;

/**
 * "Back" button for the Order Approval detail view. Returns to the Order Approvals grid.
 */
class BackButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @inheritDoc
     */
    public function getButtonData(): array
    {
        return [
            'label' => __('Back'),
            'on_click' => sprintf("location.href = '%s';", $this->getUrl('customercompliance/approval/index')),
            'class' => 'back',
            'sort_order' => 10,
        ];
    }
}
