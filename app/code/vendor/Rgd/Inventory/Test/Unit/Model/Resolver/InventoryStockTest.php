<?php
declare(strict_types=1);

namespace Rgd\Inventory\Test\Unit\Model\Resolver;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Rgd\Inventory\Model\Resolver\InventoryStock;
use Rgd\Inventory\Api\FefoBatchSelectorInterface;
use Rgd\Inventory\Api\Data\BatchInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class InventoryStockTest extends TestCase
{
    private InventoryStock $subject;
    private FefoBatchSelectorInterface&MockObject $fefoBatchSelectorMock;
    private Field&MockObject $fieldMock;
    private ContextInterface&MockObject $contextMock;
    private ResolveInfo&MockObject $resolveInfoMock;

    protected function setUp(): void
    {
        $this->fefoBatchSelectorMock = $this->createMock(FefoBatchSelectorInterface::class);
        $this->subject = new InventoryStock($this->fefoBatchSelectorMock);

        $this->fieldMock = $this->createMock(Field::class);
        $this->contextMock = $this->createMock(ContextInterface::class);
        $this->resolveInfoMock = $this->createMock(ResolveInfo::class);
    }

    /**
     * Creates a lightweight Batch mock without invoking the full constructor.
     */
    private function makeBatch(
        string $batchNumber,
        ?string $expiryDate,
        float $remainingQty,
        string $createdAt
    ): BatchInterface&MockObject {
        $batch = $this->createMock(BatchInterface::class);
        $batch->method('getBatchNumber')->willReturn($batchNumber);
        $batch->method('getExpiryDate')->willReturn($expiryDate);
        $batch->method('getRemainingQty')->willReturn($remainingQty);
        $batch->method('getCreatedAt')->willReturn($createdAt);
        return $batch;
    }

    public function testResolve_MissingSku_ThrowsGraphQlInputException(): void
    {
        $this->expectException(GraphQlInputException::class);
        $this->expectExceptionMessage('"sku" is required.');

        $this->subject->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->resolveInfoMock,
            null,
            [] // Missing 'sku' key
        );
    }

    public function testResolve_EmptySku_ThrowsGraphQlInputException(): void
    {
        $this->expectException(GraphQlInputException::class);
        $this->expectExceptionMessage('"sku" is required.');

        $this->subject->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->resolveInfoMock,
            null,
            ['sku' => '']
        );
    }

    public function testResolve_WhitespaceOnlySku_ThrowsGraphQlInputException(): void
    {
        $this->expectException(GraphQlInputException::class);
        $this->expectExceptionMessage('"sku" is required.');

        $this->subject->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->resolveInfoMock,
            null,
            ['sku' => '   ']
        );
    }

    public function testResolve_SourceCodeOmitted_CallsSelectorWithDefault(): void
    {
        $batch = $this->makeBatch('BATCH-001', '2026-08-01', 50.0, '2026-07-01 10:00:00');

        $this->fefoBatchSelectorMock->expects($this->once())
            ->method('getAvailableBatches')
            ->with('TEST-SKU', 'default')
            ->willReturn([$batch]);

        $result = $this->subject->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->resolveInfoMock,
            null,
            ['sku' => 'TEST-SKU'] // sourceCode omitted
        );

        $this->assertIsArray($result);
        $this->assertSame('TEST-SKU', $result['sku']);
    }

    public function testResolve_SourceCodeProvided_CallsSelectorWithExactValue(): void
    {
        $batch = $this->makeBatch('BATCH-001', '2026-08-01', 50.0, '2026-07-01 10:00:00');

        $this->fefoBatchSelectorMock->expects($this->once())
            ->method('getAvailableBatches')
            ->with('TEST-SKU', 'warehouse-west')
            ->willReturn([$batch]);

        $result = $this->subject->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->resolveInfoMock,
            null,
            ['sku' => 'TEST-SKU', 'sourceCode' => 'warehouse-west']
        );

        $this->assertIsArray($result);
        $this->assertSame('TEST-SKU', $result['sku']);
    }

    public function testResolve_HappyPath_MapsFieldsCorrectly(): void
    {
        $batch1 = $this->makeBatch('BATCH-001', '2026-08-01', 30.0, '2026-07-01 10:00:00');
        $batch2 = $this->makeBatch('BATCH-002', '2026-09-01', 20.0, '2026-07-02 14:30:00');

        $this->fefoBatchSelectorMock->expects($this->once())
            ->method('getAvailableBatches')
            ->willReturn([$batch1, $batch2]);

        $result = $this->subject->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->resolveInfoMock,
            null,
            ['sku' => 'TEST-SKU']
        );

        // Verify structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('sku', $result);
        $this->assertArrayHasKey('available_qty', $result);
        $this->assertArrayHasKey('batches', $result);

        // Verify sku echoes trimmed input
        $this->assertSame('TEST-SKU', $result['sku']);

        // Verify available_qty is sum of remaining quantities
        $this->assertSame(50.0, $result['available_qty']);

        // Verify batches array structure and field mapping
        $this->assertCount(2, $result['batches']);

        $this->assertSame('BATCH-001', $result['batches'][0]['batch_number']);
        $this->assertSame('2026-08-01', $result['batches'][0]['expiry_date']);
        $this->assertSame(30.0, $result['batches'][0]['available_qty']);
        $this->assertSame('2026-07-01 10:00:00', $result['batches'][0]['received_at']);

        $this->assertSame('BATCH-002', $result['batches'][1]['batch_number']);
        $this->assertSame('2026-09-01', $result['batches'][1]['expiry_date']);
        $this->assertSame(20.0, $result['batches'][1]['available_qty']);
        $this->assertSame('2026-07-02 14:30:00', $result['batches'][1]['received_at']);
    }

    public function testResolve_SelectorReturnsEmptyArray_ReturnsZeroQtyAndEmptyBatches(): void
    {
        $this->fefoBatchSelectorMock->expects($this->once())
            ->method('getAvailableBatches')
            ->willReturn([]);

        $result = $this->subject->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->resolveInfoMock,
            null,
            ['sku' => 'UNKNOWN-SKU']
        );

        // "No usable stock" is not an error — it returns a valid response with zero qty
        $this->assertIsArray($result);
        $this->assertSame('UNKNOWN-SKU', $result['sku']);
        $this->assertSame(0.0, $result['available_qty']);
        $this->assertSame([], $result['batches']);
    }

    public function testResolve_NullExpiryBatchIncluded_MapsNullExpiryDateToNull(): void
    {
        $datedBatch = $this->makeBatch('BATCH-001', '2026-08-01', 30.0, '2026-07-01 10:00:00');
        $nullExpiryBatch = $this->makeBatch('BATCH-NO-EXPIRY', null, 20.0, '2026-07-02 14:30:00');

        $this->fefoBatchSelectorMock->expects($this->once())
            ->method('getAvailableBatches')
            ->willReturn([$datedBatch, $nullExpiryBatch]);

        $result = $this->subject->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->resolveInfoMock,
            null,
            ['sku' => 'TEST-SKU']
        );

        $this->assertCount(2, $result['batches']);
        $this->assertSame('2026-08-01', $result['batches'][0]['expiry_date']);
        $this->assertNull($result['batches'][1]['expiry_date']);
    }
}
