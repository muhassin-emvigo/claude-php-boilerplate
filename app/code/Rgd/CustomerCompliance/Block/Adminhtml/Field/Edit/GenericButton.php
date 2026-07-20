<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Block\Adminhtml\Field\Edit;

use Magento\Backend\Block\Widget\Context;

/**
 * Shared helpers for Field edit-page ui_component form buttons.
 *
 * NOTE: this class (and its Save/Back button siblings in this namespace) was not among the
 * files explicitly listed as "already created" for this task, but a working field_form.xml
 * needs a Save and a Back button wired the same way Group Config's edit form wires
 * Block\Adminhtml\GroupConfig\Edit\{GenericButton,SaveButton,BackButton}. This class mirrors
 * that trio exactly, scoped to the Field entity/routes instead.
 */
class GenericButton
{
    /**
     * @param Context $context
     */
    public function __construct(
        protected readonly Context $context
    ) {
    }

    /**
     * Get the current field_id from the request, if any.
     *
     * @return int|null
     */
    public function getFieldId(): ?int
    {
        $fieldId = $this->context->getRequest()->getParam('field_id');

        return $fieldId ? (int)$fieldId : null;
    }

    /**
     * Get the current config_id (owning Group Config) from the request, if any.
     *
     * @return int|null
     */
    public function getConfigId(): ?int
    {
        $configId = $this->context->getRequest()->getParam('config_id');

        return $configId ? (int)$configId : null;
    }

    /**
     * Build an admin URL.
     *
     * @param string $route
     * @param array $params
     * @return string
     */
    public function getUrl(string $route = '*/*/', array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
