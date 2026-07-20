<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Block\Adminhtml\Field\Edit;

use Magento\Ui\Component\Control\ButtonProviderInterface;

/**
 * "Save" button for the Field edit/new form. Submits via the standard Ui/Component form "save"
 * client event, which posts the form's data to the dataSource's submitUrl
 * (customercompliance/field/save).
 */
class SaveButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @inheritDoc
     */
    public function getButtonData(): array
    {
        return [
            'label' => __('Save Field'),
            'class' => 'save primary',
            'data_attribute' => [
                'mage-init' => ['button' => ['event' => 'save']],
                'form-role' => 'save',
            ],
            'sort_order' => 90,
        ];
    }
}
