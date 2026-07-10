<?php
declare(strict_types=1);

namespace Rgd\Inventory\Test\Unit\Ui\DataProvider;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Rgd\Inventory\Ui\DataProvider\BatchDataProvider;
use Rgd\Inventory\Model\ResourceModel\Batch\CollectionFactory;
use Rgd\Inventory\Model\ResourceModel\Batch\Collection;
use Rgd\Inventory\Model\Data\Batch;
use Magento\Framework\Api\Filter;
use ArrayIterator;

class BatchDataProviderTest extends TestCase
{
    private CollectionFactory&MockObject $collectionFactoryMock;
    private Collection&MockObject $collectionMock;

    protected function setUp(): void
    {
        $this->collectionFactoryMock = $this->createMock(CollectionFactory::class);
        $this->collectionMock = $this->createMock(Collection::class);

        $this->collectionFactoryMock->method('create')->willReturn($this->collectionMock);
    }

    private function createSubject(): BatchDataProvider
    {
        return new BatchDataProvider(
            'rgd_inventory_batch_form_data_source',
            'batch_id',
            'batch_id',
            $this->collectionFactoryMock
        );
    }

    /**
     * "Add New Batch" always triggers Form::getDataSourceData(), which adds a
     * primary-key filter with an empty/null value even for a brand-new record.
     * addFilter() must skip it (not touch the collection), and getData() must
     * return an empty array immediately without loading the collection at all.
     */
    public function testGetData_NewRecordWithNoRealFilterApplied_ReturnsEmptyArrayWithoutLoadingCollection(): void
    {
        $subject = $this->createSubject();
        $subject->addFilter(new Filter(['field' => 'batch_id', 'value' => null]));

        $this->collectionMock->expects($this->never())->method('load');
        $this->collectionMock->expects($this->never())->method('isLoaded');
        $this->collectionMock->expects($this->never())->method('getIterator');

        $this->assertSame([], $subject->getData());
    }

    /**
     * addFilter() with an empty-string value (the other shape
     * Form::getDataSourceData() can send) must be skipped the same way as null.
     */
    public function testAddFilter_EmptyStringValue_IsSkipped(): void
    {
        $subject = $this->createSubject();
        $subject->addFilter(new Filter(['field' => 'batch_id', 'value' => '']));

        $this->collectionMock->expects($this->never())->method('load');

        $this->assertSame([], $subject->getData());
    }

    /**
     * The edit path (a real batch_id filter value) must still load the
     * collection and return the [$id => $itemData] shape the form expects —
     * this is the behavior the fix must not break.
     */
    public function testGetData_EditRecordWithRealFilterApplied_LoadsCollectionAndReturnsItemDataKeyedById(): void
    {
        $itemMock = $this->getMockBuilder(Batch::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData'])
            ->getMock();
        $itemMock->method('getData')->willReturnCallback(
            static function (...$args) {
                $data = ['batch_id' => 1, 'sku' => 'SKU-1'];
                $key = $args[0] ?? null;
                return ($key === null || $key === '') ? $data : ($data[$key] ?? null);
            }
        );

        $subject = $this->createSubject();
        $subject->addFilter(new Filter(['field' => 'batch_id', 'value' => 5]));

        $this->collectionMock->method('isLoaded')->willReturn(false);
        $this->collectionMock->expects($this->once())->method('load');
        $this->collectionMock->method('getIterator')->willReturnCallback(
            fn () => new ArrayIterator([$itemMock])
        );

        $result = $subject->getData();

        $this->assertSame(['batch_id' => 1, 'sku' => 'SKU-1'], $result[1]);
    }
}
