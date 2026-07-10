<?php
declare(strict_types=1);

namespace Rgd\Inventory\Test\Unit\Ui\DataProvider;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Rgd\Inventory\Ui\DataProvider\BatchListingDataProvider;
use Rgd\Inventory\Model\ResourceModel\Batch\CollectionFactory;
use Rgd\Inventory\Model\ResourceModel\Batch\Collection;

class BatchListingDataProviderTest extends TestCase
{
    private CollectionFactory&MockObject $collectionFactoryMock;
    private Collection&MockObject $collectionMock;

    protected function setUp(): void
    {
        $this->collectionFactoryMock = $this->createMock(CollectionFactory::class);
        $this->collectionMock = $this->createMock(Collection::class);
    }

    private function createSubject(): BatchListingDataProvider
    {
        $this->collectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($this->collectionMock);

        return new BatchListingDataProvider(
            'rgd_inventory_batch_listing_data_source',
            'batch_id',
            'batch_id',
            $this->collectionFactoryMock
        );
    }

    public function testConstruct_BuildsCollectionFromFactory_ExactlyOnce(): void
    {
        $subject = $this->createSubject();

        $this->assertSame($this->collectionMock, $subject->getCollection());
    }

    public function testGetName_ReturnsNameProvidedToConstructor(): void
    {
        $subject = $this->createSubject();

        $this->assertSame('rgd_inventory_batch_listing_data_source', $subject->getName());
    }

    public function testGetPrimaryFieldName_ReturnsFieldProvidedToConstructor(): void
    {
        $subject = $this->createSubject();

        $this->assertSame('batch_id', $subject->getPrimaryFieldName());
    }

    public function testGetData_DelegatesToCollectionToArray_ReturnsListingShape(): void
    {
        $expected = ['items' => [['batch_id' => 1]], 'totalRecords' => 1];
        $this->collectionMock->method('toArray')->willReturn($expected);

        $subject = $this->createSubject();

        // Deliberately does NOT override getData() — must delegate to the
        // inherited AbstractDataProvider::getData() ({items, totalRecords} shape
        // the grid JS component expects), unlike BatchDataProvider (form
        // provider), which overrides getData() for the [$id => $itemData] shape.
        $this->assertSame($expected, $subject->getData());
    }

    public function testCount_DelegatesToCollectionCount(): void
    {
        $this->collectionMock->method('count')->willReturn(3);

        $subject = $this->createSubject();

        $this->assertSame(3, $subject->count());
    }
}
