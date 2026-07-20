<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Block\Adminhtml\GroupConfig\Edit;

use Magento\Backend\Block\Widget\Context;

/**
 * Shared helpers for Group Configuration edit-page ui_component form buttons.
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
     * Get the current config_id from the request, if any.
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
