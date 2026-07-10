<?php
declare(strict_types=1);

namespace Rgd\Inventory\Test\Unit\Controller\Adminhtml\Batch;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Rgd\Inventory\Controller\Adminhtml\Batch\Save;
use Rgd\Inventory\Api\BatchRepositoryInterface;
use Rgd\Inventory\Model\Data\Batch;
use Rgd\Inventory\Model\Data\BatchFactory;
use Rgd\Inventory\Model\Data\BatchTransactionFactory;
use Rgd\Inventory\Model\ResourceModel\BatchTransaction as BatchTransactionResourceModel;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\RedirectFactory;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryApi\Api\SourceRepositoryInterface;

/**
 * Focused unit coverage for Save::validateSourceCode() (the W2 fix). The rest
 * of this controller has no pre-existing unit test harness (a known,
 * pre-existing gap, not addressed here) - this suite deliberately covers only
 * the new source_code validation branch rather than building a full
 * execute()-path harness for unrelated behavior.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) A controller unit test that
 *     mocks every constructor collaborator (Context plus 5 direct
 *     dependencies) is expected to reference this many classes.
 */
class SaveTest extends TestCase
{
    private Save $subject;

    private Context&MockObject $contextMock;
    private BatchRepositoryInterface&MockObject $batchRepositoryMock;
    private BatchFactory&MockObject $batchFactoryMock;
    private BatchTransactionFactory&MockObject $transactionFactoryMock;
    private BatchTransactionResourceModel&MockObject $transactionResourceModelMock;
    private SourceRepositoryInterface&MockObject $sourceRepositoryMock;

    private HttpRequest&MockObject $requestMock;
    private ManagerInterface&MockObject $messageManagerMock;
    private RedirectFactory&MockObject $resultRedirectFactoryMock;
    private Redirect&MockObject $resultRedirectMock;

    protected function setUp(): void
    {
        $this->batchRepositoryMock = $this->createMock(BatchRepositoryInterface::class);
        $this->batchFactoryMock = $this->createMock(BatchFactory::class);
        $this->transactionFactoryMock = $this->createMock(BatchTransactionFactory::class);
        $this->transactionResourceModelMock = $this->createMock(BatchTransactionResourceModel::class);
        $this->sourceRepositoryMock = $this->createMock(SourceRepositoryInterface::class);

        $this->requestMock = $this->createMock(HttpRequest::class);
        $this->messageManagerMock = $this->createMock(ManagerInterface::class);
        $this->resultRedirectMock = $this->createMock(Redirect::class);
        $this->resultRedirectFactoryMock = $this->createMock(RedirectFactory::class);
        $this->resultRedirectFactoryMock->method('create')->willReturn($this->resultRedirectMock);

        $this->contextMock = $this->createMock(Context::class);
        $this->contextMock->method('getRequest')->willReturn($this->requestMock);
        $this->contextMock->method('getMessageManager')->willReturn($this->messageManagerMock);
        $this->contextMock->method('getResultRedirectFactory')->willReturn($this->resultRedirectFactoryMock);

        $this->subject = new Save(
            $this->contextMock,
            $this->batchRepositoryMock,
            $this->batchFactoryMock,
            $this->transactionFactoryMock,
            $this->transactionResourceModelMock,
            $this->sourceRepositoryMock
        );
    }

    private function makeBatchMock(array $postData): Batch&MockObject
    {
        $batch = $this->getMockBuilder(Batch::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'setSku',
                'setBatchNumber',
                'setSourceCode',
                'setExpiryDate',
                'setReceivedQty',
                'setRemainingQty',
                'setIsActive',
                'getBatchNumber',
                'getSourceCode',
                'getExpiryDate',
                'getReceivedQty',
                'getRemainingQty',
            ])
            ->getMock();

        $batch->method('setSku')->willReturnSelf();
        $batch->method('setBatchNumber')->willReturnSelf();
        $batch->method('setSourceCode')->willReturnSelf();
        $batch->method('setExpiryDate')->willReturnSelf();
        $batch->method('setReceivedQty')->willReturnSelf();
        $batch->method('setRemainingQty')->willReturnSelf();
        $batch->method('setIsActive')->willReturnSelf();

        $batch->method('getBatchNumber')->willReturn((string)($postData['batch_number'] ?? ''));
        $batch->method('getSourceCode')->willReturn((string)($postData['source_code'] ?? ''));
        $batch->method('getExpiryDate')->willReturn($postData['expiry_date'] ?? null);
        $batch->method('getReceivedQty')->willReturn((float)($postData['received_qty'] ?? 0));
        $batch->method('getRemainingQty')->willReturn((float)($postData['remaining_qty'] ?? 0));

        return $batch;
    }

    private function wireRequest(array $postData): void
    {
        $this->requestMock->method('getPostValue')->willReturnCallback(
            static function (?string $key = null) use ($postData) {
                return $key === null ? $postData : ($postData[$key] ?? null);
            }
        );
    }

    public function testExecute_NonExistentSourceCode_AddsErrorMessageAndDoesNotSave(): void
    {
        $postData = [
            'batch_id' => 0,
            'sku' => 'TEST-SKU',
            'batch_number' => 'BATCH-001',
            'source_code' => 'bogus-source',
            'expiry_date' => '2026-12-31',
            'received_qty' => '10',
            'remaining_qty' => '10',
            'is_active' => '1',
        ];
        $this->wireRequest($postData);

        $batch = $this->makeBatchMock($postData);
        $this->batchFactoryMock->method('create')->willReturn($batch);

        $this->sourceRepositoryMock->expects($this->once())
            ->method('get')
            ->with('bogus-source')
            ->willThrowException(new NoSuchEntityException(__('Source does not exist.')));

        $this->batchRepositoryMock->expects($this->never())->method('save');

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with($this->stringContains('Source "bogus-source" does not exist.'));

        $this->resultRedirectMock->expects($this->once())
            ->method('setPath')
            ->with('rgd_inventory/batch/edit', ['batch_id' => 0])
            ->willReturnSelf();

        $this->subject->execute();
    }

    public function testExecute_EmptySourceCode_AddsErrorMessageAndDoesNotSave(): void
    {
        $postData = [
            'batch_id' => 0,
            'sku' => 'TEST-SKU',
            'batch_number' => 'BATCH-001',
            'source_code' => '',
            'expiry_date' => '2026-12-31',
            'received_qty' => '10',
            'remaining_qty' => '10',
            'is_active' => '1',
        ];
        $this->wireRequest($postData);

        $batch = $this->makeBatchMock($postData);
        $this->batchFactoryMock->method('create')->willReturn($batch);

        $this->sourceRepositoryMock->expects($this->never())->method('get');
        $this->batchRepositoryMock->expects($this->never())->method('save');

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with($this->stringContains('Source is required.'));

        $this->resultRedirectMock->expects($this->once())
            ->method('setPath')
            ->with('rgd_inventory/batch/edit', ['batch_id' => 0])
            ->willReturnSelf();

        $this->subject->execute();
    }

    public function testExecute_ValidSourceCode_PassesValidationAndProceedsToSave(): void
    {
        $postData = [
            'batch_id' => 0,
            'sku' => 'TEST-SKU',
            'batch_number' => 'BATCH-001',
            'source_code' => 'default',
            'expiry_date' => '2026-12-31',
            'received_qty' => '10',
            'remaining_qty' => '10',
            'is_active' => '1',
        ];
        $this->wireRequest($postData);

        $batch = $this->makeBatchMock($postData);
        $this->batchFactoryMock->method('create')->willReturn($batch);

        $this->sourceRepositoryMock->expects($this->once())
            ->method('get')
            ->with('default')
            ->willReturn($this->createMock(SourceInterface::class));

        $this->batchRepositoryMock->expects($this->once())
            ->method('save')
            ->willThrowException(new CouldNotSaveException(__('stop here')));

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with('stop here');

        $this->resultRedirectMock->method('setPath')->willReturnSelf();

        $this->subject->execute();
    }
}
