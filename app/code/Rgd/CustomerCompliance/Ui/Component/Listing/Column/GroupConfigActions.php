<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Row actions renderer for the Group Configuration grid ("Edit" only per the Design doc — group
 * configs are not deletable one-by-one from the grid; one row exists per configured customer
 * group).
 */
class GroupConfigActions extends Column
{
    private const URL_PATH_EDIT = 'customercompliance/groupconfig/edit';

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
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
            if (!isset($item['config_id'])) {
                continue;
            }

            $item[$name]['edit'] = [
                'href' => $this->urlBuilder->getUrl(self::URL_PATH_EDIT, ['config_id' => $item['config_id']]),
                'label' => __('Edit'),
            ];
        }

        return $dataSource;
    }
}
