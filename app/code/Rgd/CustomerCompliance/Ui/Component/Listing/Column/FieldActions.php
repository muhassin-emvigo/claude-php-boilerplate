<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Row actions renderer for the Compliance Fields grid ("Edit" and "Delete", ACL-guarded).
 */
class FieldActions extends Column
{
    private const URL_PATH_EDIT = 'customercompliance/field/edit';
    private const URL_PATH_DELETE = 'customercompliance/field/delete';

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param \Magento\Framework\AuthorizationInterface $authorization
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly \Magento\Framework\AuthorizationInterface $authorization,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @inheritDoc
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $name = $this->getData('name');

        foreach ($dataSource['data']['items'] as & $item) {
            if (!isset($item['field_id'])) {
                continue;
            }

            if ($this->authorization->isAllowed('Rgd_CustomerCompliance::field')) {
                $item[$name]['edit'] = [
                    'href' => $this->urlBuilder->getUrl(
                        self::URL_PATH_EDIT,
                        ['field_id' => $item['field_id'], 'config_id' => $item['config_id'] ?? null]
                    ),
                    'label' => __('Edit'),
                ];

                $item[$name]['delete'] = [
                    'href' => $this->urlBuilder->getUrl(
                        self::URL_PATH_DELETE,
                        ['field_id' => $item['field_id'], 'config_id' => $item['config_id'] ?? null]
                    ),
                    'label' => __('Delete'),
                    'confirm' => [
                        'title' => __('Delete Field'),
                        'message' => __(
                            'Are you sure you want to delete field "%1"? This cannot be undone.',
                            $item['label'] ?? $item['field_id']
                        ),
                    ],
                ];
            }
        }

        return $dataSource;
    }
}
