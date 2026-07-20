<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Block\Adminhtml\GroupConfig\Edit;

use Magento\Ui\Component\Control\ButtonProviderInterface;

/**
 * "Back" button for the Group Configuration edit/new form.
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
            'on_click' => sprintf("location.href = '%s';", $this->getUrl('customercompliance/groupconfig/index')),
            'class' => 'back',
            'sort_order' => 10,
        ];
    }
}
