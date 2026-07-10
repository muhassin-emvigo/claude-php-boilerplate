<?php
declare(strict_types=1);

namespace Rgd\Inventory\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\UrlInterface;

/**
 * Actions column for batch listing grid
 */
class Actions extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private UrlInterface $urlBuilder,
        array $components = [],
        array $data = [],
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                if (isset($item['batch_id'])) {
                    $item[$this->getData('name')] = [
                        'edit' => [
                            'href' => $this->urlBuilder->getUrl('rgd_inventory/batch/edit', ['batch_id' => $item['batch_id']]),
                            'label' => __('Edit'),
                        ],
                        'delete' => [
                            'href' => $this->urlBuilder->getUrl('rgd_inventory/batch/delete', ['batch_id' => $item['batch_id']]),
                            'label' => __('Delete'),
                            'confirm' => [
                                'title' => __('Delete Batch'),
                                'message' => __('Are you sure you want to delete this batch?'),
                            ],
                            // Delete.php implements HttpPostActionInterface (POST-only).
                            // Without 'post' => true, Magento_Ui/js/grid/columns/actions'
                            // defaultCallback() falls through to
                            // window.location.href = action.href — a plain GET navigation
                            // that a POST-only controller will reject. 'post' => true makes
                            // it submit via mage/dataPost instead (same convention as core,
                            // e.g. Magento_Cms's PageActions/BlockActions delete actions).
                            'post' => true,
                        ],
                    ];
                }
            }
        }

        return $dataSource;
    }
}
