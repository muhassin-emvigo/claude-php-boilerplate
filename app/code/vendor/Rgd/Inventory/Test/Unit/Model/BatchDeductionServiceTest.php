<?php
declare(strict_types=1);

namespace Rgd\Inventory\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Rgd\Inventory\Model\BatchDeductionService;
use Rgd\Inventory\Model\Data\BatchTransaction;
use Rgd\Inventory\Model\Data\BatchTransactionFactory;
use Rgd\Inventory\Model\ResourceModel\Batch as BatchResourceModel;
use Rgd\Inventory\Model\ResourceModel\BatchTransaction as BatchTransactionResourceModel;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\CouldNotSaveException;
use RuntimeException;

class BatchDeductionServiceTest extends TestCase
{
    private BatchDeductionService $subject;

    private BatchTransactionFactory&MockObject $transactionFactoryMock;
    private BatchTransactionResourceModel&MockObject $transactionResourceModelMock;
    private BatchResourceModel&MockObject $batchResourceModelMock;
    private AdapterInterface&MockObject $connectionMock;

    protected function setUp(): void
    {
        $this->transactionFactoryMock = $this->createMock(BatchTransactionFactory::class);
        $this->transactionResourceModelMock = $this->createMock(BatchTransactionResourceModel::class);
        $this->batchResourceModelMock = $this->createMock(BatchResourceModel::class);
        $this->connectionMock = $this->createMock(AdapterInterface::class);

        $this->batchResourceModelMock->method('getConnection')->willReturn($this->connectionMock);
        $this->batchResourceModelMock->method('getMainTable')->willReturn('rgd_inventory_batch');

        $this->subject = new BatchDeductionService(
            $this->transactionFactoryMock,
            $this->transactionResourceModelMock,
            $this->batchResourceModelMock
        );
    }

    private function makeSalesEvent(string $type): SalesEventInterface&MockObject
    {
        $salesEvent = $this->createMock(SalesEventInterface::class);
        $salesEvent->method('getType')->willReturn($type);

        return $salesEvent;
    }

    /**
     * Wires the connection's select()/fetchAll() calls the way allocateWithLock()
     * issues them: (1) locked FEFO candidate rows, (2) all-active rows (existence
     * check), (3) expired rows (diagnostics).
     *
     * @param array<int, array{batch_id:int, batch_number:string, expiry_date:?string, remaining_qty:float}> $lockedRows
     * @param array $allRows
     * @param array $expiredRows
     */
    private function wireConnectionRows(array $lockedRows, array $allRows, array $expiredRows): void
    {
        $selectMock = $this->createMock(Select::class);
        $selectMock->method('from')->willReturnSelf();
        $selectMock->method('where')->willReturnSelf();
        $selectMock->method('order')->willReturnSelf();
        $selectMock->method('forUpdate')->willReturnSelf();

        $this->connectionMock->method('select')->willReturn($selectMock);
        $this->connectionMock->method('fetchAll')->willReturnOnConsecutiveCalls(
            $lockedRows,
            $allRows,
            $expiredRows
        );
    }

    public function testDeduct_SuccessfulSingleBatchDeduction_DecrementsRemainingQtyAndWritesOneAuditRow(): void
    {
        $lockedRows = [
            ['batch_id' => 1, 'batch_number' => 'BATCH-001', 'expiry_date' => '2026-08-01', 'remaining_qty' => '100.0000'],
        ];
        $this->wireConnectionRows($lockedRows, $lockedRows, []);

        $this->connectionMock->expects($this->once())->method('beginTransaction');
        $this->connectionMock->expects($this->once())->method('commit');
        $this->connectionMock->expects($this->never())->method('rollBack');

        $this->connectionMock->expects($this->once())
            ->method('update')
            ->with(
                'rgd_inventory_batch',
                $this->callback(fn (array $bind) => isset($bind['remaining_qty'])),
                [
                    'batch_id = ?' => 1,
                    'remaining_qty >= ?' => 40.0,
                ]
            )
            ->willReturn(1);

        $transaction = $this->createMock(BatchTransaction::class);
        $transaction->method('setBatchId')->willReturnSelf();
        $transaction->method('setSku')->willReturnSelf();
        $transaction->method('setBatchNumber')->willReturnSelf();
        $transaction->method('setExpiryDate')->willReturnSelf();
        $transaction->method('setMovementType')->willReturnSelf();
        $transaction->method('setQty')->willReturnSelf();
        $transaction->method('setSalesEventType')->willReturnSelf();
        $transaction->method('setOrderId')->willReturnSelf();
        $transaction->method('setOrderItemId')->willReturnSelf();
        $transaction->method('setReference')->willReturnSelf();

        $this->transactionFactoryMock->expects($this->once())->method('create')->willReturn($transaction);
        $this->transactionResourceModelMock->expects($this->once())->method('save')->with($transaction);

        $salesEvent = $this->makeSalesEvent('shipment_created');

        $allocations = $this->subject->deduct('SKU-1', 40.0, $salesEvent, 55, 'default');

        $this->assertCount(1, $allocations);
        $this->assertSame(1, $allocations[0]->getBatchId());
        $this->assertSame(40.0, $allocations[0]->getQty());
    }

    public function testDeduct_SplitBatchDeduction_WritesOneTransactionRowPerBatchSharingOrderItemId(): void
    {
        $lockedRows = [
            ['batch_id' => 1, 'batch_number' => 'BATCH-A', 'expiry_date' => '2026-07-10', 'remaining_qty' => '30.0000'],
            ['batch_id' => 2, 'batch_number' => 'BATCH-B', 'expiry_date' => '2026-09-01', 'remaining_qty' => '50.0000'],
        ];
        $this->wireConnectionRows($lockedRows, $lockedRows, []);

        $this->connectionMock->expects($this->once())->method('beginTransaction');
        $this->connectionMock->expects($this->once())->method('commit');
        $this->connectionMock->expects($this->exactly(2))->method('update')->willReturn(1);

        $capturedOrderItemIds = [];

        $transaction1 = $this->createMock(BatchTransaction::class);
        $transaction2 = $this->createMock(BatchTransaction::class);

        foreach ([$transaction1, $transaction2] as $t) {
            $t->method('setBatchId')->willReturnSelf();
            $t->method('setSku')->willReturnSelf();
            $t->method('setBatchNumber')->willReturnSelf();
            $t->method('setExpiryDate')->willReturnSelf();
            $t->method('setMovementType')->willReturnSelf();
            $t->method('setQty')->willReturnSelf();
            $t->method('setSalesEventType')->willReturnSelf();
            $t->method('setReference')->willReturnSelf();
            $t->method('setOrderItemId')->willReturnCallback(function ($id) use ($t, &$capturedOrderItemIds) {
                $capturedOrderItemIds[] = $id;
                return $t;
            });
            $t->method('setOrderId')->willReturnSelf();
        }

        $this->transactionFactoryMock->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($transaction1, $transaction2);

        $this->transactionResourceModelMock->expects($this->exactly(2))
            ->method('save')
            ->with($this->logicalOr($transaction1, $transaction2));

        $salesEvent = $this->makeSalesEvent('shipment_created');

        $allocations = $this->subject->deduct('SKU-2', 60.0, $salesEvent, 99, 'default');

        $this->assertCount(2, $allocations);
        $this->assertSame([99, 99], $capturedOrderItemIds);
    }

    public function testDeduct_FailureMidAllocation_RollsBackWithNoPartialWrites(): void
    {
        $lockedRows = [
            ['batch_id' => 1, 'batch_number' => 'BATCH-001', 'expiry_date' => '2026-08-01', 'remaining_qty' => '100.0000'],
        ];
        $this->wireConnectionRows($lockedRows, $lockedRows, []);

        $this->connectionMock->expects($this->once())->method('beginTransaction');
        $this->connectionMock->expects($this->once())->method('rollBack');
        $this->connectionMock->expects($this->never())->method('commit');

        $this->connectionMock->method('update')->willThrowException(new RuntimeException('DB deadlock'));

        $this->transactionFactoryMock->expects($this->never())->method('create');
        $this->transactionResourceModelMock->expects($this->never())->method('save');

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('Unable to record batch deduction for SKU "SKU-3": DB deadlock');

        $salesEvent = $this->makeSalesEvent('shipment_created');

        $this->subject->deduct('SKU-3', 40.0, $salesEvent, 1, 'default');
    }

    public function testDeduct_NoBatchesExistForSku_RollsBackAndThrowsNoBatchInventoryConfiguredException(): void
    {
        $this->wireConnectionRows([], [], []);

        $this->connectionMock->expects($this->once())->method('beginTransaction');
        $this->connectionMock->expects($this->once())->method('rollBack');
        $this->connectionMock->expects($this->never())->method('commit');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('No batch inventory configured for SKU "SKU-4".');

        $salesEvent = $this->makeSalesEvent('shipment_created');

        $this->subject->deduct('SKU-4', 10.0, $salesEvent, null, 'default');
    }

    public function testDeduct_RequestedQtyExceedsLockedAvailability_RollsBackAndThrowsInsufficientStockException(): void
    {
        $lockedRows = [
            ['batch_id' => 1, 'batch_number' => 'BATCH-001', 'expiry_date' => '2026-08-01', 'remaining_qty' => '10.0000'],
        ];
        $this->wireConnectionRows($lockedRows, $lockedRows, []);

        $this->connectionMock->expects($this->once())->method('beginTransaction');
        $this->connectionMock->expects($this->once())->method('rollBack');
        $this->connectionMock->expects($this->never())->method('commit');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Insufficient batch stock for SKU "SKU-5": requested 50, available 10.');

        $salesEvent = $this->makeSalesEvent('shipment_created');

        $this->subject->deduct('SKU-5', 50.0, $salesEvent, null, 'default');
    }

    public function testDeduct_UpdateAffectsZeroRows_RollsBackAndThrowsLocalizedException(): void
    {
        // Simulates the row lock somehow not preventing a concurrent drain: the
        // defensive `remaining_qty >= ?` guard on the UPDATE matches 0 rows.
        $lockedRows = [
            ['batch_id' => 1, 'batch_number' => 'BATCH-001', 'expiry_date' => '2026-08-01', 'remaining_qty' => '100.0000'],
        ];
        $this->wireConnectionRows($lockedRows, $lockedRows, []);

        $this->connectionMock->expects($this->once())->method('beginTransaction');
        $this->connectionMock->expects($this->once())->method('rollBack');
        $this->connectionMock->expects($this->never())->method('commit');

        $this->connectionMock->expects($this->once())->method('update')->willReturn(0);

        $this->transactionFactoryMock->expects($this->never())->method('create');
        $this->transactionResourceModelMock->expects($this->never())->method('save');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'Unable to deduct 40 unit(s) from batch ID "1": remaining quantity changed unexpectedly.'
        );

        $salesEvent = $this->makeSalesEvent('shipment_created');

        $this->subject->deduct('SKU-6', 40.0, $salesEvent, 1, 'default');
    }

    public function testDeductWithinTransaction_Success_DoesNotManageTransactionBoundary(): void
    {
        $lockedRows = [
            ['batch_id' => 1, 'batch_number' => 'BATCH-001', 'expiry_date' => '2026-08-01', 'remaining_qty' => '100.0000'],
        ];
        $this->wireConnectionRows($lockedRows, $lockedRows, []);

        // The caller (coordinator) owns the transaction — deductWithinTransaction()
        // must never call beginTransaction/commit/rollBack itself.
        $this->connectionMock->expects($this->never())->method('beginTransaction');
        $this->connectionMock->expects($this->never())->method('commit');
        $this->connectionMock->expects($this->never())->method('rollBack');

        $this->connectionMock->expects($this->once())->method('update')->willReturn(1);

        $transaction = $this->createMock(BatchTransaction::class);
        $transaction->method('setBatchId')->willReturnSelf();
        $transaction->method('setSku')->willReturnSelf();
        $transaction->method('setBatchNumber')->willReturnSelf();
        $transaction->method('setExpiryDate')->willReturnSelf();
        $transaction->method('setMovementType')->willReturnSelf();
        $transaction->method('setQty')->willReturnSelf();
        $transaction->method('setSalesEventType')->willReturnSelf();
        $transaction->method('setOrderId')->willReturnSelf();
        $transaction->method('setOrderItemId')->willReturnSelf();
        $transaction->method('setReference')->willReturnSelf();

        $this->transactionFactoryMock->expects($this->once())->method('create')->willReturn($transaction);
        $this->transactionResourceModelMock->expects($this->once())->method('save')->with($transaction);

        $salesEvent = $this->makeSalesEvent('shipment_created');

        $allocations = $this->subject->deductWithinTransaction(
            'SKU-7',
            40.0,
            $salesEvent,
            55,
            'default',
            $this->connectionMock
        );

        $this->assertCount(1, $allocations);
        $this->assertSame(40.0, $allocations[0]->getQty());
    }

    public function testDeductWithinTransaction_InsufficientStock_ThrowsWithoutTouchingTransactionBoundary(): void
    {
        $lockedRows = [
            ['batch_id' => 1, 'batch_number' => 'BATCH-001', 'expiry_date' => '2026-08-01', 'remaining_qty' => '10.0000'],
        ];
        $this->wireConnectionRows($lockedRows, $lockedRows, []);

        // The failure must propagate to the caller so IT can roll back the shared
        // transaction; deductWithinTransaction() itself must not touch the boundary.
        $this->connectionMock->expects($this->never())->method('beginTransaction');
        $this->connectionMock->expects($this->never())->method('commit');
        $this->connectionMock->expects($this->never())->method('rollBack');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Insufficient batch stock for SKU "SKU-8": requested 50, available 10.');

        $salesEvent = $this->makeSalesEvent('shipment_created');

        $this->subject->deductWithinTransaction('SKU-8', 50.0, $salesEvent, null, 'default', $this->connectionMock);
    }
}
