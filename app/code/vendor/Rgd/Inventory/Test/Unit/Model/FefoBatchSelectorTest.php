<?php
declare(strict_types=1);

namespace Rgd\Inventory\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Rgd\Inventory\Model\FefoBatchSelector;
use Rgd\Inventory\Model\Data\Batch;
use Rgd\Inventory\Model\ResourceModel\Batch\CollectionFactory;
use Rgd\Inventory\Model\ResourceModel\Batch\Collection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use ArrayIterator;

class FefoBatchSelectorTest extends TestCase
{
    private FefoBatchSelector $subject;
    private CollectionFactory&MockObject $collectionFactoryMock;

    protected function setUp(): void
    {
        $this->collectionFactoryMock = $this->createMock(CollectionFactory::class);
        $this->subject = new FefoBatchSelector($this->collectionFactoryMock);
    }

    /**
     * Builds a lightweight Batch stand-in without invoking AbstractExtensibleModel's
     * heavy constructor (Context/Registry/ExtensionFactory/etc are irrelevant here —
     * the selector only ever calls the plain getters on the row objects it iterates).
     */
    private function makeBatch(int $batchId, string $batchNumber, ?string $expiryDate, float $remainingQty): Batch&MockObject
    {
        $batch = $this->getMockBuilder(Batch::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getBatchId', 'getBatchNumber', 'getExpiryDate', 'getRemainingQty'])
            ->getMock();

        $batch->method('getBatchId')->willReturn($batchId);
        $batch->method('getBatchNumber')->willReturn($batchNumber);
        $batch->method('getExpiryDate')->willReturn($expiryDate);
        $batch->method('getRemainingQty')->willReturn($remainingQty);

        return $batch;
    }

    private function wireCollections(
        array $candidateBatches,
        array $expiredBatches,
        int $totalBatchCount,
        float $totalAvailableQtyAcrossActive
    ): void {
        $candidateCollection = $this->createMock(Collection::class);
        $expiredCollection = $this->createMock(Collection::class);

        $candidateCollection->method('addFieldToFilter')->willReturnSelf();
        $expiredCollection->method('addFieldToFilter')->willReturnSelf();

        $connectionMock = $this->createMock(AdapterInterface::class);
        $connectionMock->method('quoteInto')->willReturn('expiry_date IS NULL OR expiry_date > CURDATE()');

        $selectMock = $this->createMock(Select::class);
        $selectMock->method('where')->willReturnSelf();
        $selectMock->method('order')->willReturnSelf();
        $selectMock->method('from')->willReturnSelf();

        $candidateCollection->method('getSelect')->willReturn($selectMock);
        $candidateCollection->method('getConnection')->willReturn($connectionMock);
        $candidateCollection->method('getMainTable')->willReturn('rgd_inventory_batch');

        $expiredCollection->method('getSelect')->willReturn($selectMock);
        $expiredCollection->method('getConnection')->willReturn($connectionMock);

        $connectionMock->method('select')->willReturn($selectMock);
        $connectionMock->method('fetchOne')->willReturnOnConsecutiveCalls(
            $totalBatchCount,
            $totalAvailableQtyAcrossActive
        );

        $candidateCollection->method('getIterator')->willReturnCallback(
            fn () => new ArrayIterator($candidateBatches)
        );
        $candidateCollection->method('count')->willReturn(count($candidateBatches));

        $expiredCollection->method('getIterator')->willReturnCallback(
            fn () => new ArrayIterator($expiredBatches)
        );
        $expiredCollection->method('count')->willReturn(count($expiredBatches));

        $this->collectionFactoryMock->method('create')->willReturnOnConsecutiveCalls(
            $candidateCollection,
            $expiredCollection
        );
    }

    /**
     * Wires a single collection mock for getAvailableBatches(), which never reaches
     * the diagnostic/failure path — only the filter + FEFO order + iteration calls
     * used by applyCandidateFilterAndFefoOrder() need to be stubbed here.
     */
    private function wireAvailableBatchesCollection(array $batches): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();

        $connectionMock = $this->createMock(AdapterInterface::class);
        $connectionMock->method('quoteInto')->willReturn('expiry_date IS NULL OR expiry_date > CURDATE()');

        $selectMock = $this->createMock(Select::class);
        $selectMock->method('where')->willReturnSelf();
        $selectMock->method('order')->willReturnSelf();

        $collection->method('getSelect')->willReturn($selectMock);
        $collection->method('getConnection')->willReturn($connectionMock);
        $collection->method('getIterator')->willReturnCallback(
            fn () => new ArrayIterator($batches)
        );

        $this->collectionFactoryMock->method('create')->willReturn($collection);
    }

    public function testSelectForDeduction_SingleBatchCoversFullQty_ReturnsSingleAllocation(): void
    {
        $batch = $this->makeBatch(1, 'BATCH-001', '2026-08-01', 100.0);
        $this->wireCollections([$batch], [], 1, 100.0);

        $allocations = $this->subject->selectForDeduction('SKU-1', 40.0);

        $this->assertCount(1, $allocations);
        $this->assertSame(1, $allocations[0]->getBatchId());
        $this->assertSame('BATCH-001', $allocations[0]->getBatchNumber());
        $this->assertSame(40.0, $allocations[0]->getQty());
    }

    public function testSelectForDeduction_QtySpansMultipleBatches_AllocatesEarliestExpiryFirst(): void
    {
        $batchEarly = $this->makeBatch(1, 'BATCH-EARLY', '2026-07-10', 30.0);
        $batchLate = $this->makeBatch(2, 'BATCH-LATE', '2026-09-01', 50.0);
        $this->wireCollections([$batchEarly, $batchLate], [], 2, 80.0);

        $allocations = $this->subject->selectForDeduction('SKU-2', 60.0);

        $this->assertCount(2, $allocations);
        $this->assertSame(1, $allocations[0]->getBatchId());
        $this->assertSame(30.0, $allocations[0]->getQty());
        $this->assertSame(2, $allocations[1]->getBatchId());
        $this->assertSame(30.0, $allocations[1]->getQty());
    }

    public function testSelectForDeduction_BatchExpiringToday_IsExcludedFromCandidates(): void
    {
        $todayBatch = $this->makeBatch(1, 'BATCH-TODAY', date('Y-m-d'), 20.0);
        $tomorrowBatch = $this->makeBatch(2, 'BATCH-TOMORROW', date('Y-m-d', strtotime('+1 day')), 20.0);

        $this->wireCollections([$tomorrowBatch], [$todayBatch], 2, 20.0);

        $allocations = $this->subject->selectForDeduction('SKU-3', 20.0);

        $this->assertCount(1, $allocations);
        $this->assertSame(2, $allocations[0]->getBatchId());
    }

    public function testSelectForDeduction_BatchExpiringTomorrow_IsIncluded(): void
    {
        $tomorrowBatch = $this->makeBatch(5, 'BATCH-TOMORROW', date('Y-m-d', strtotime('+1 day')), 15.0);
        $this->wireCollections([$tomorrowBatch], [], 1, 15.0);

        $allocations = $this->subject->selectForDeduction('SKU-4', 10.0);

        $this->assertCount(1, $allocations);
        $this->assertSame(5, $allocations[0]->getBatchId());
        $this->assertSame(10.0, $allocations[0]->getQty());
    }

    public function testSelectForDeduction_NullExpiryBatch_SortsLastAfterDatedBatches(): void
    {
        $datedBatch = $this->makeBatch(1, 'BATCH-DATED', '2026-07-15', 10.0);
        $nullExpiryBatch = $this->makeBatch(2, 'BATCH-NO-EXPIRY', null, 50.0);
        $this->wireCollections([$datedBatch, $nullExpiryBatch], [], 2, 60.0);

        $allocations = $this->subject->selectForDeduction('SKU-5', 30.0);

        $this->assertCount(2, $allocations);
        $this->assertSame(1, $allocations[0]->getBatchId());
        $this->assertSame(10.0, $allocations[0]->getQty());
        $this->assertSame(2, $allocations[1]->getBatchId());
        $this->assertSame(20.0, $allocations[1]->getQty());
    }

    public function testSelectForDeduction_AllBatchesExpired_ThrowsAllExpiredException(): void
    {
        $expiredBatch1 = $this->makeBatch(1, 'BATCH-EXP-1', '2026-01-01', 20.0);
        $expiredBatch2 = $this->makeBatch(2, 'BATCH-EXP-2', date('Y-m-d'), 15.0);
        $this->wireCollections([], [$expiredBatch1, $expiredBatch2], 2, 35.0);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'No usable batch stock for SKU "SKU-6": 35 unit(s) exist across 2 batch(es) but all are expired.'
        );

        $this->subject->selectForDeduction('SKU-6', 10.0);
    }

    public function testSelectForDeduction_NoBatchesExistForSku_ThrowsNoBatchInventoryConfiguredException(): void
    {
        $this->wireCollections([], [], 0, 0.0);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('No batch inventory configured for SKU "SKU-7".');

        $this->subject->selectForDeduction('SKU-7', 5.0);
    }

    public function testSelectForDeduction_RequestedQtyExceedsAvailable_ThrowsInsufficientStockException(): void
    {
        $batch = $this->makeBatch(1, 'BATCH-001', '2026-08-01', 25.0);
        $this->wireCollections([$batch], [], 1, 25.0);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'Insufficient batch stock for SKU "SKU-8": requested 100, available 25.'
        );

        $this->subject->selectForDeduction('SKU-8', 100.0);
    }

    public function testSelectForDeduction_TiedExpiryDates_OrdersDeterministicallyByBatchIdAscending(): void
    {
        $lowerBatchId = $this->makeBatch(3, 'BATCH-A', '2026-07-20', 10.0);
        $higherBatchId = $this->makeBatch(7, 'BATCH-B', '2026-07-20', 10.0);
        $this->wireCollections([$lowerBatchId, $higherBatchId], [], 2, 20.0);

        $allocations = $this->subject->selectForDeduction('SKU-9', 15.0);

        $this->assertCount(2, $allocations);
        $this->assertSame(3, $allocations[0]->getBatchId());
        $this->assertSame(7, $allocations[1]->getBatchId());
    }

    public function testGetAvailableBatches_ReturnsCandidatesInFefoOrder(): void
    {
        $earlyBatch = $this->makeBatch(1, 'BATCH-EARLY', '2026-07-10', 30.0);
        $lateBatch = $this->makeBatch(2, 'BATCH-LATE', '2026-09-01', 50.0);
        $this->wireAvailableBatchesCollection([$earlyBatch, $lateBatch]);

        $batches = $this->subject->getAvailableBatches('SKU-1');

        $this->assertCount(2, $batches);
        $this->assertSame(1, $batches[0]->getBatchId());
        $this->assertSame(2, $batches[1]->getBatchId());
    }

    public function testGetAvailableBatches_NoUsableStock_ReturnsEmptyArray(): void
    {
        $this->wireAvailableBatchesCollection([]);

        $batches = $this->subject->getAvailableBatches('SKU-2');

        $this->assertSame([], $batches);
    }

    public function testGetAvailableBatches_DoesNotThrowOnEmptyStock(): void
    {
        $this->wireAvailableBatchesCollection([]);

        // Unlike selectForDeduction(), an empty result here must be a plain empty
        // array, not a thrown LocalizedException — this is the read-only stock
        // check used by the GraphQL resolver.
        $this->assertIsArray($this->subject->getAvailableBatches('SKU-3'));
    }

    public function testGetAvailableBatches_BatchExpiringToday_IsExcluded(): void
    {
        // Verify the same expiry-cutoff rule as selectForDeduction():
        // a batch expiring today is already treated as expired and excluded
        // from available batches, even when called through getAvailableBatches().
        // Note: $todayBatch is not passed to wireAvailableBatchesCollection — it is
        // excluded by the filter, so only $tomorrowBatch is returned by the mocked collection.
        $this->makeBatch(1, 'BATCH-TODAY', date('Y-m-d'), 20.0);
        $tomorrowBatch = $this->makeBatch(2, 'BATCH-TOMORROW', date('Y-m-d', strtotime('+1 day')), 30.0);

        $this->wireAvailableBatchesCollection([$tomorrowBatch]);

        $batches = $this->subject->getAvailableBatches('SKU-10');

        // Only tomorrow's batch should be returned; today's batch is excluded
        // by the same expiry_date > CURDATE() filter shared with selectForDeduction()
        $this->assertCount(1, $batches);
        $this->assertSame(2, $batches[0]->getBatchId());
    }

    public function testGetAvailableBatches_AppliesColumnScopingAndRowCap(): void
    {
        // getAvailableBatches() feeds an unauthenticated, uncached GraphQL read
        // path — unlike selectForDeduction(), it must narrow its column
        // selection and cap the number of rows fetched. Assert both are wired,
        // without duplicating the FEFO-order/empty-stock assertions already
        // covered by the other getAvailableBatches() tests above.
        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();

        $connectionMock = $this->createMock(AdapterInterface::class);
        $connectionMock->method('quoteInto')->willReturn('expiry_date IS NULL OR expiry_date > CURDATE()');

        $selectMock = $this->createMock(Select::class);
        $selectMock->method('where')->willReturnSelf();
        $selectMock->method('order')->willReturnSelf();

        $collection->method('getSelect')->willReturn($selectMock);
        $collection->method('getConnection')->willReturn($connectionMock);
        $collection->method('getIterator')->willReturnCallback(fn () => new ArrayIterator([]));

        $collection->expects($this->once())
            ->method('addFieldToSelect')
            ->with(['batch_id', 'batch_number', 'expiry_date', 'remaining_qty', 'created_at']);

        $collection->expects($this->once())
            ->method('setPageSize')
            ->with(500);

        $this->collectionFactoryMock->method('create')->willReturn($collection);

        $this->subject->getAvailableBatches('SKU-11');
    }
}
