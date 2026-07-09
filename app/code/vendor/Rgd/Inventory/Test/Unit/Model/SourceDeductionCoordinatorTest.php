<?php
declare(strict_types=1);

namespace Rgd\Inventory\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Rgd\Inventory\Model\SourceDeductionCoordinator;
use Rgd\Inventory\Model\BatchDeductionService;
use Rgd\Inventory\Model\ResourceModel\Batch as BatchResourceModel;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionRequestInterface;
use Magento\InventorySourceDeductionApi\Model\ItemToDeductInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Verifies the coordinator owns ONE shared transaction across the whole item loop
 * AND the proceed() call — the core of the HIGH fix (atomicity with MSI, and
 * all-or-nothing across multi-item deductions).
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) A unit test that fully mocks every
 *     collaborator of SourceDeductionCoordinator plus MSI contract types
 *     (SourceDeductionRequestInterface, ItemToDeductInterface, SalesEventInterface) and
 *     PHPUnit/test infrastructure is expected to reference this many classes — that is
 *     the nature of comprehensive constructor-mock coverage, not a design smell.
 */
class SourceDeductionCoordinatorTest extends TestCase
{
    private SourceDeductionCoordinator $subject;

    private BatchDeductionService&MockObject $batchDeductionServiceMock;
    private OrderItemRepositoryInterface&MockObject $orderItemRepositoryMock;
    private SearchCriteriaBuilder&MockObject $searchCriteriaBuilderMock;
    private LoggerInterface&MockObject $loggerMock;
    private BatchResourceModel&MockObject $batchResourceModelMock;
    private AdapterInterface&MockObject $connectionMock;

    protected function setUp(): void
    {
        $this->batchDeductionServiceMock = $this->createMock(BatchDeductionService::class);
        $this->orderItemRepositoryMock = $this->createMock(OrderItemRepositoryInterface::class);
        $this->searchCriteriaBuilderMock = $this->createMock(SearchCriteriaBuilder::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->batchResourceModelMock = $this->createMock(BatchResourceModel::class);
        $this->connectionMock = $this->createMock(AdapterInterface::class);

        $this->batchResourceModelMock->method('getConnection')->willReturn($this->connectionMock);

        $this->subject = new SourceDeductionCoordinator(
            $this->batchDeductionServiceMock,
            $this->orderItemRepositoryMock,
            $this->searchCriteriaBuilderMock,
            $this->loggerMock,
            $this->batchResourceModelMock
        );
    }

    private function makeItem(string $sku, float $qty): ItemToDeductInterface&MockObject
    {
        $item = $this->createMock(ItemToDeductInterface::class);
        $item->method('getSku')->willReturn($sku);
        $item->method('getQty')->willReturn($qty);

        return $item;
    }

    private function makeRequest(array $items): SourceDeductionRequestInterface&MockObject
    {
        $salesEvent = $this->createMock(SalesEventInterface::class);
        $salesEvent->method('getType')->willReturn('shipment_created');
        // Not order-bound, so resolveOrderItemId() short-circuits to null without
        // touching the order item repository — keeps this test focused on the
        // transaction-boundary behavior under test.
        $salesEvent->method('getObjectType')->willReturn('not_order');

        $request = $this->createMock(SourceDeductionRequestInterface::class);
        $request->method('getItems')->willReturn($items);
        $request->method('getSourceCode')->willReturn('default');
        $request->method('getSalesEvent')->willReturn($salesEvent);

        return $request;
    }

    public function testExecuteWithBatchTracking_AllItemsAndProceedSucceed_CommitsOnce(): void
    {
        $items = [$this->makeItem('SKU-1', 5.0), $this->makeItem('SKU-2', 3.0)];
        $request = $this->makeRequest($items);

        $this->connectionMock->expects($this->once())->method('beginTransaction');
        $this->connectionMock->expects($this->once())->method('commit');
        $this->connectionMock->expects($this->never())->method('rollBack');

        $this->batchDeductionServiceMock->expects($this->exactly(2))
            ->method('deductWithinTransaction')
            ->with(
                $this->isType('string'),
                $this->isType('float'),
                $this->anything(),
                null,
                'default',
                $this->connectionMock
            )
            ->willReturn([]);

        $proceedCalled = false;
        $proceed = function ($req) use (&$proceedCalled, $request) {
            $proceedCalled = true;
            $this->assertSame($request, $req);
        };

        $this->subject->executeWithBatchTracking($request, $proceed);

        $this->assertTrue($proceedCalled, 'proceed() should have been called within the shared transaction');
    }

    public function testExecuteWithBatchTracking_SecondItemDeductionFails_RollsBackAndNeverCallsProceed(): void
    {
        $items = [$this->makeItem('SKU-1', 5.0), $this->makeItem('SKU-2', 999.0)];
        $request = $this->makeRequest($items);

        $this->connectionMock->expects($this->once())->method('beginTransaction');
        $this->connectionMock->expects($this->never())->method('commit');
        $this->connectionMock->expects($this->once())->method('rollBack');

        $this->batchDeductionServiceMock->expects($this->exactly(2))
            ->method('deductWithinTransaction')
            ->willReturnCallback(function (string $sku) {
                if ($sku === 'SKU-2') {
                    throw new LocalizedException(__('Insufficient batch stock for SKU "SKU-2".'));
                }
                return [];
            });

        $proceedCalled = false;
        $proceed = function () use (&$proceedCalled) {
            $proceedCalled = true;
        };

        $this->expectException(LocalizedException::class);

        try {
            $this->subject->executeWithBatchTracking($request, $proceed);
        } finally {
            $this->assertFalse($proceedCalled, 'proceed() must never run when an earlier item fails');
        }
    }

    public function testExecuteWithBatchTracking_ProceedThrows_RollsBackAfterAllItemDeductionsSucceeded(): void
    {
        $items = [$this->makeItem('SKU-1', 5.0)];
        $request = $this->makeRequest($items);

        $this->connectionMock->expects($this->once())->method('beginTransaction');
        $this->connectionMock->expects($this->never())->method('commit');
        $this->connectionMock->expects($this->once())->method('rollBack');

        $this->batchDeductionServiceMock->expects($this->once())
            ->method('deductWithinTransaction')
            ->willReturn([]);

        $proceed = function () {
            throw new RuntimeException('MSI proceed() failed');
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MSI proceed() failed');

        $this->subject->executeWithBatchTracking($request, $proceed);
    }
}
