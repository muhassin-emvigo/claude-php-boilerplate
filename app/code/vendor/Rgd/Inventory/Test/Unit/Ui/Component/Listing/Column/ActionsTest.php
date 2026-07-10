<?php
declare(strict_types=1);

namespace Rgd\Inventory\Test\Unit\Ui\Component\Listing\Column;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Rgd\Inventory\Ui\Component\Listing\Column\Actions;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\UrlInterface;

class ActionsTest extends TestCase
{
    private UrlInterface&MockObject $urlBuilderMock;
    private Actions $subject;

    protected function setUp(): void
    {
        $contextMock = $this->createMock(ContextInterface::class);
        $uiComponentFactoryMock = $this->createMock(UiComponentFactory::class);
        $this->urlBuilderMock = $this->createMock(UrlInterface::class);

        $this->subject = new Actions($contextMock, $uiComponentFactoryMock, $this->urlBuilderMock);
        $this->subject->setData('name', 'actions');
    }

    /**
     * Delete.php implements HttpPostActionInterface (POST-only). Without
     * 'post' => true on the rendered action, Magento_Ui/js/grid/columns/actions'
     * defaultCallback() falls through to a plain GET navigation
     * (window.location.href), which a POST-only controller rejects — the
     * grid's Delete link would silently not work. Assert it is present.
     */
    public function testPrepareDataSource_DeleteAction_IsMarkedAsPost(): void
    {
        $this->urlBuilderMock->method('getUrl')->willReturnCallback(
            static fn (string $path, array $params = []): string => $path . '?batch_id=' . $params['batch_id']
        );

        $dataSource = [
            'data' => [
                'items' => [
                    ['batch_id' => 5],
                ],
            ],
        ];

        $result = $this->subject->prepareDataSource($dataSource);

        $this->assertTrue($result['data']['items'][0]['actions']['delete']['post']);
    }

    public function testPrepareDataSource_EditAction_HasExpectedUrl(): void
    {
        $this->urlBuilderMock->method('getUrl')->willReturnCallback(
            static fn (string $path, array $params = []): string => $path . '?batch_id=' . $params['batch_id']
        );

        $dataSource = [
            'data' => [
                'items' => [
                    ['batch_id' => 5],
                ],
            ],
        ];

        $result = $this->subject->prepareDataSource($dataSource);

        $this->assertSame(
            'rgd_inventory/batch/edit?batch_id=5',
            $result['data']['items'][0]['actions']['edit']['href']
        );
    }

    public function testPrepareDataSource_ItemWithoutBatchId_IsUntouched(): void
    {
        $dataSource = [
            'data' => [
                'items' => [
                    ['some_other_field' => 'value'],
                ],
            ],
        ];

        $result = $this->subject->prepareDataSource($dataSource);

        $this->assertArrayNotHasKey('actions', $result['data']['items'][0]);
    }
}
