<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Block\Adminhtml\Field\Edit;

use Magento\Ui\Component\Control\ButtonProviderInterface;

/**
 * "Back" button for the Field edit/new form. Returns to the Fields grid scoped to the owning
 * Group Config, matching how this grid is normally reached (as a tab on the Group Config edit
 * page per the Design doc).
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
            'on_click' => sprintf(
                "location.href = '%s';",
                $this->getUrl('customercompliance/field/index', ['config_id' => $this->getConfigId()])
            ),
            'class' => 'back',
            'sort_order' => 10,
        ];
    }
}
