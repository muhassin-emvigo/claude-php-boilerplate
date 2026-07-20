<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Block\Adminhtml\GroupConfig\Edit;

use Magento\Ui\Component\Control\ButtonProviderInterface;

/**
 * "Save" button for the Group Configuration edit/new form. Submits via the standard
 * Ui/Component form "save" client event, which posts the form's data to the dataSource's
 * submitUrl (customercompliance/groupconfig/save).
 */
class SaveButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @inheritDoc
     */
    public function getButtonData(): array
    {
        return [
            'label' => __('Save Group Config'),
            'class' => 'save primary',
            'data_attribute' => [
                'mage-init' => ['button' => ['event' => 'save']],
                'form-role' => 'save',
            ],
            'sort_order' => 90,
        ];
    }
}
