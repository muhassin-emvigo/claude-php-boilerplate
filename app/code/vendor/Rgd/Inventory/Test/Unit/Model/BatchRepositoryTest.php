<?php
declare(strict_types=1);

namespace Rgd\Inventory\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Rgd\Inventory\Model\BatchRepository;
use Rgd\Inventory\Model\Data\Batch;
use Rgd\Inventory\Model\Data\BatchFactory;
use Rgd\Inventory\Api\Data\BatchSearchResultsInterfaceFactory;
use Rgd\Inventory\Model\ResourceModel\Batch\CollectionFactory;
use Rgd\Inventory\Model\ResourceModel\Batch as BatchResourceModel;
use Rgd\Inventory\Model\ResourceModel\BatchTransaction as BatchTransactionResourceModel;
use Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) A unit test that fully mocks every
 *     collaborator of BatchRepository (6 constructor dependencies) plus PHPUnit/test
 *     infrastructure types is expected to reference this many classes — that is the
 *     nature of comprehensive constructor-mock coverage, not a design smell in the test.
 */
class BatchRepositoryTest extends TestCase
{
    private BatchRepository $subject;

    private BatchFactory&MockObject $batchFactoryMock;
    private BatchSearchResultsInterfaceFactory&MockObject $searchResultsFactoryMock;
    private CollectionFactory&MockObject $collectionFactoryMock;
    private BatchResourceModel&MockObject $batchResourceModelMock;
    private BatchTransactionResourceModel&MockObject $transactionResourceModelMock;
    private JoinProcessorInterface&MockObject $joinProcessorMock;

    protected function setUp(): void
    {
        $this->batchFactoryMock = $this->createMock(BatchFactory::class);
        $this->searchResultsFactoryMock = $this->createMock(BatchSearchResultsInterfaceFactory::class);
        $this->collectionFactoryMock = $this->createMock(CollectionFactory::class);
        $this->batchResourceModelMock = $this->createMock(BatchResourceModel::class);
        $this->transactionResourceModelMock = $this->createMock(BatchTransactionResourceModel::class);
        $this->joinProcessorMock = $this->createMock(JoinProcessorInterface::class);

        $this->subject = new BatchRepository(
            $this->batchFactoryMock,
            $this->searchResultsFactoryMock,
            $this->collectionFactoryMock,
            $this->batchResourceModelMock,
            $this->transactionResourceModelMock,
            $this->joinProcessorMock
        );
    }

    private function makeBatch(int $batchId, string $batchNumber): Batch&MockObject
    {
        $batch = $this->getMockBuilder(Batch::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getBatchNumber', 'getBatchId'])
            ->getMock();

        $batch->method('getId')->willReturn($batchId);
        $batch->method('getBatchId')->willReturn($batchId);
        $batch->method('getBatchNumber')->willReturn($batchNumber);

        return $batch;
    }

    private function wireTransactionHistoryCheck(bool $hasHistory): void
    {
        $connectionMock = $this->createMock(AdapterInterface::class);
        $selectMock = $this->createMock(Select::class);
        $selectMock->method('from')->willReturnSelf();
        $selectMock->method('where')->willReturnSelf();
        $selectMock->method('limit')->willReturnSelf();

        $connectionMock->method('select')->willReturn($selectMock);
        $connectionMock->method('fetchOne')->willReturn($hasHistory ? '1' : false);

        $this->transactionResourceModelMock->method('getConnection')->willReturn($connectionMock);
        $this->transactionResourceModelMock->method('getMainTable')->willReturn('rgd_inventory_batch_transaction');
    }


    public function testDeleteById_BatchHasTransactionHistory_ThrowsCouldNotDeleteException(): void
    {
        $batch = $this->makeBatch(10, 'BATCH-010');
        $this->batchFactoryMock->method('create')->willReturn($batch);
        $this->batchResourceModelMock->expects($this->once())->method('load')->with($batch, 10);

        $this->wireTransactionHistoryCheck(true);

        $this->batchResourceModelMock->expects($this->never())->method('delete');

        $this->expectException(CouldNotDeleteException::class);
        $this->expectExceptionMessage(
            'Cannot delete batch "BATCH-010": it has recorded inventory transactions. Deactivate it instead.'
        );

        $this->subject->deleteById(10);
    }

    public function testDeleteById_BatchHasNoTransactionHistory_DeletesSuccessfully(): void
    {
        $batch = $this->makeBatch(11, 'BATCH-011');
        $this->batchFactoryMock->method('create')->willReturn($batch);
        $this->batchResourceModelMock->expects($this->once())->method('load')->with($batch, 11);

        $this->wireTransactionHistoryCheck(false);

        $this->batchResourceModelMock->expects($this->once())->method('delete')->with($batch);

        $result = $this->subject->deleteById(11);

        $this->assertTrue($result);
    }

    public function testDelete_DelegatesToDeleteByIdUsingBatchId(): void
    {
        $batchToDelete = $this->makeBatch(12, 'BATCH-012');

        $loadedBatch = $this->makeBatch(12, 'BATCH-012');
        $this->batchFactoryMock->method('create')->willReturn($loadedBatch);
        $this->batchResourceModelMock->expects($this->once())->method('load')->with($loadedBatch, 12);

        $this->wireTransactionHistoryCheck(false);

        $this->batchResourceModelMock->expects($this->once())->method('delete')->with($loadedBatch);

        $result = $this->subject->delete($batchToDelete);

        $this->assertTrue($result);
    }

    private function makeSaveableBatch(
        float $receivedQty,
        float $remainingQty,
        ?string $expiryDate
    ): Batch&MockObject {
        // Mock the concrete Batch model (not just BatchInterface) because
        // BatchResourceModel::save() is type-hinted against AbstractModel.
        $batch = $this->getMockBuilder(Batch::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getReceivedQty', 'getRemainingQty', 'getExpiryDate', 'getBatchNumber', 'getSku', 'getSourceCode'])
            ->getMock();

        $batch->method('getReceivedQty')->willReturn($receivedQty);
        $batch->method('getRemainingQty')->willReturn($remainingQty);
        $batch->method('getExpiryDate')->willReturn($expiryDate);
        $batch->method('getBatchNumber')->willReturn('BATCH-X');
        $batch->method('getSku')->willReturn('SKU-X');
        $batch->method('getSourceCode')->willReturn('default');

        return $batch;
    }

    public function testSave_NegativeReceivedQty_ThrowsCouldNotSaveExceptionWithoutPersisting(): void
    {
        $batch = $this->makeSaveableBatch(-5.0, 0.0, null);

        $this->batchResourceModelMock->expects($this->never())->method('save');

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('Cannot save batch: received quantity cannot be negative.');

        $this->subject->save($batch);
    }

    public function testSave_NegativeRemainingQty_ThrowsCouldNotSaveExceptionWithoutPersisting(): void
    {
        $batch = $this->makeSaveableBatch(10.0, -1.0, null);

        $this->batchResourceModelMock->expects($this->never())->method('save');

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('Cannot save batch: remaining quantity cannot be negative.');

        $this->subject->save($batch);
    }

    public function testSave_RemainingQtyExceedsReceivedQty_ThrowsCouldNotSaveExceptionWithoutPersisting(): void
    {
        $batch = $this->makeSaveableBatch(10.0, 20.0, null);

        $this->batchResourceModelMock->expects($this->never())->method('save');

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('Cannot save batch: remaining quantity cannot exceed received quantity.');

        $this->subject->save($batch);
    }

    /**
     * @dataProvider invalidExpiryDateProvider
     */
    public function testSave_InvalidExpiryDateFormat_ThrowsCouldNotSaveExceptionWithoutPersisting(string $expiryDate): void
    {
        $batch = $this->makeSaveableBatch(10.0, 5.0, $expiryDate);

        $this->batchResourceModelMock->expects($this->never())->method('save');

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage(
            'Cannot save batch: expiry date "' . $expiryDate . '" must be in Y-m-d format.'
        );

        $this->subject->save($batch);
    }

    public static function invalidExpiryDateProvider(): array
    {
        return [
            'wrong separator' => ['2026/08/01'],
            'datetime with time component' => ['2026-08-01 00:00:00'],
            'not a real date' => ['2026-02-30'],
            'garbage string' => ['not-a-date'],
        ];
    }

    public function testSave_ValidBatch_PersistsSuccessfully(): void
    {
        $batch = $this->makeSaveableBatch(10.0, 5.0, '2026-08-01');

        $this->batchResourceModelMock->expects($this->once())->method('save')->with($batch);

        $result = $this->subject->save($batch);

        $this->assertSame($batch, $result);
    }

    public function testSave_NullExpiryDate_PersistsSuccessfully(): void
    {
        $batch = $this->makeSaveableBatch(10.0, 5.0, null);

        $this->batchResourceModelMock->expects($this->once())->method('save')->with($batch);

        $result = $this->subject->save($batch);

        $this->assertSame($batch, $result);
    }
}
