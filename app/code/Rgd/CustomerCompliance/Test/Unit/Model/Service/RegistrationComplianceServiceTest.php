<?php

// NOTE: Written in parallel with Model\Service\RegistrationComplianceService. Constructor
// argument order below is a best-effort reconstruction from the shared Eng spec/interface list.
// Re-run and adjust (constructor arg order, exact FieldValue setter names, etc.) against the
// actual implementation during Build-stage integration.

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Test\Unit\Model\Service;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Filesystem\Io\File as FilesystemIoFile;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Rgd\CustomerCompliance\Api\AuditLoggerInterface;
use Rgd\CustomerCompliance\Api\Data\DocumentInterface;
use Rgd\CustomerCompliance\Api\Data\FieldInterface;
use Rgd\CustomerCompliance\Api\Data\FieldValueInterface;
use Rgd\CustomerCompliance\Api\Data\UploadedFileInterface;
use Rgd\CustomerCompliance\Api\DocumentStorageServiceInterface;
use Rgd\CustomerCompliance\Api\FieldConfigProviderInterface;
use Rgd\CustomerCompliance\Exception\BusinessRuleException;
use Rgd\CustomerCompliance\Model\FieldValueFactory;
use Rgd\CustomerCompliance\Model\ResourceModel\FieldValue as FieldValueResource;
use Rgd\CustomerCompliance\Model\Service\RegistrationComplianceService;
use Rgd\CustomerCompliance\Model\Service\UploadedFileNormalizer;

/**
 * @covers \Rgd\CustomerCompliance\Model\Service\RegistrationComplianceService
 */
class RegistrationComplianceServiceTest extends TestCase
{
    private FieldConfigProviderInterface&MockObject $fieldConfigProvider;
    private DocumentStorageServiceInterface&MockObject $documentStorageService;
    private UploadedFileNormalizer&MockObject $uploadedFileNormalizer;
    private FieldValueFactory&MockObject $fieldValueFactory;
    private FieldValueResource&MockObject $fieldValueResource;
    private AuditLoggerInterface&MockObject $auditLogger;
    private ResourceConnection&MockObject $resourceConnection;
    private CustomerRepositoryInterface&MockObject $customerRepository;
    private LoggerInterface&MockObject $logger;
    private TransportBuilder&MockObject $transportBuilder;
    private StoreManagerInterface&MockObject $storeManager;
    private DataObjectFactory&MockObject $dataObjectFactory;
    private AdapterInterface&MockObject $adapter;
    private FilesystemIoFile&MockObject $filesystemIoFile;

    protected function setUp(): void
    {
        $this->fieldConfigProvider = $this->createMock(FieldConfigProviderInterface::class);
        $this->documentStorageService = $this->createMock(DocumentStorageServiceInterface::class);
        // normalize() is a pass-through for this module's own UploadedFileInterface DTOs (see
        // UploadedFileNormalizer's bug-fix PHPDoc) - every uploaded-file double in this test
        // already implements that DTO interface, so returning the same instance back is exactly
        // what the real (fixed) implementation does.
        $this->uploadedFileNormalizer = $this->createMock(UploadedFileNormalizer::class);
        $this->uploadedFileNormalizer->method('normalize')->willReturnArgument(1);
        $this->fieldValueFactory = $this->createMock(FieldValueFactory::class);
        $this->fieldValueResource = $this->createMock(FieldValueResource::class);
        $this->auditLogger = $this->createMock(AuditLoggerInterface::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->transportBuilder = $this->createMock(TransportBuilder::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->dataObjectFactory = $this->createMock(DataObjectFactory::class);

        $this->adapter = $this->createMock(AdapterInterface::class);
        $this->resourceConnection->method('getConnection')->willReturn($this->adapter);

        $this->filesystemIoFile = $this->createMock(FilesystemIoFile::class);
        $this->filesystemIoFile->method('getPathInfo')->willReturnCallback(
            static fn (string $path): array => pathinfo($path)
        );

        // sendRegistrationSuccessEmail() is best-effort/wrapped in try-catch(\Throwable), but
        // configure it fully rather than relying on that swallow, so a real customer lookup
        // failure can't mask an unrelated test failure.
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getFirstname')->willReturn('Jane');
        $customer->method('getLastname')->willReturn('Doe');
        $customer->method('getEmail')->willReturn('jane@example.com');
        $this->customerRepository->method('getById')->willReturn($customer);
        $this->dataObjectFactory->method('create')->willReturn($this->createMock(DataObject::class));
    }

    private function createService(): RegistrationComplianceService
    {
        return new RegistrationComplianceService(
            $this->fieldConfigProvider,
            $this->documentStorageService,
            $this->uploadedFileNormalizer,
            $this->fieldValueFactory,
            $this->fieldValueResource,
            $this->auditLogger,
            $this->resourceConnection,
            $this->customerRepository,
            $this->transportBuilder,
            $this->storeManager,
            $this->dataObjectFactory,
            $this->logger,
            $this->filesystemIoFile
        );
    }

    /**
     * @param string $code
     * @param bool $required
     * @param string $fieldType
     * @param string|null $allowedExtensions
     * @param int|null $maxFileSizeKb
     * @return FieldInterface&MockObject
     */
    private function createField(
        string $code,
        bool $required,
        string $fieldType = 'text',
        ?string $allowedExtensions = null,
        ?int $maxFileSizeKb = null
    ): FieldInterface&MockObject {
        $field = $this->createMock(FieldInterface::class);
        $field->method('getFieldCode')->willReturn($code);
        $field->method('isRequired')->willReturn($required);
        $field->method('getFieldType')->willReturn($fieldType);
        $field->method('getAllowedExtensions')->willReturn($allowedExtensions);
        $field->method('getMaxFileSizeKb')->willReturn($maxFileSizeKb);
        $field->method('getFieldId')->willReturn(1);

        return $field;
    }

    /**
     * @param string $code
     * @param string $originalName
     * @param int $sizeBytes
     * @return UploadedFileInterface&MockObject
     */
    private function createUploadedFile(
        string $code,
        string $originalName,
        int $sizeBytes
    ): UploadedFileInterface&MockObject {
        $file = $this->createMock(UploadedFileInterface::class);
        $file->method('getFieldCode')->willReturn($code);
        $file->method('getOriginalName')->willReturn($originalName);
        $file->method('getTmpName')->willReturn('/tmp/php' . $code);
        $file->method('getMimeType')->willReturn('application/octet-stream');
        $file->method('getSize')->willReturn($sizeBytes);

        return $file;
    }

    /**
     * @return FieldValueInterface&MockObject
     */
    private function createFieldValueDouble(): FieldValueInterface&MockObject
    {
        $fieldValue = $this->createMock(FieldValueInterface::class);
        $fieldValue->method('setCustomerId')->willReturnSelf();
        $fieldValue->method('setFieldId')->willReturnSelf();
        $fieldValue->method('setValue')->willReturnSelf();
        $fieldValue->method('setDocumentId')->willReturnSelf();
        $fieldValue->method('setValueId')->willReturnSelf();

        return $fieldValue;
    }

    public function testThrowsBusinessRuleExceptionWhenRequiredFieldIsMissing(): void
    {
        $field = $this->createField('id_proof', true, 'file', 'pdf,jpg', 2048);
        $this->fieldConfigProvider->method('getFieldsForGroup')->with(4)->willReturn([$field]);

        $this->expectException(BusinessRuleException::class);

        $this->createService()->processRegistration(10, 4, [], []);
    }

    public function testDoesNotThrowWhenAllRequiredFieldsAreSatisfied(): void
    {
        $field = $this->createField('full_name', true, 'text');
        $this->fieldConfigProvider->method('getFieldsForGroup')->willReturn([$field]);

        $fieldValue = $this->createFieldValueDouble();
        $this->fieldValueFactory->method('create')->willReturn($fieldValue);
        $this->fieldValueResource->expects($this->atLeastOnce())->method('save');

        $this->createService()->processRegistration(10, 4, ['full_name' => 'John Doe'], []);

        // Reaching this line without an exception is itself the primary assertion; also
        // confirm the resource save actually happened rather than the loop short-circuiting.
        $this->addToAssertionCount(1);
    }

    public function testThrowsBusinessRuleExceptionWhenFileExtensionIsNotAllowed(): void
    {
        $field = $this->createField('id_proof', false, 'file', 'pdf,jpg,png', 5000);
        $this->fieldConfigProvider->method('getFieldsForGroup')->willReturn([$field]);

        $uploadedFile = $this->createUploadedFile('id_proof', 'malware.exe', 1000);

        $this->expectException(BusinessRuleException::class);

        $this->createService()->processRegistration(10, 4, [], ['id_proof' => $uploadedFile]);
    }

    public function testThrowsBusinessRuleExceptionWhenFileSizeExceedsMax(): void
    {
        $field = $this->createField('id_proof', false, 'file', 'pdf', 500);
        $this->fieldConfigProvider->method('getFieldsForGroup')->willReturn([$field]);

        // 600 KB, exceeds the 500 KB max configured above.
        $uploadedFile = $this->createUploadedFile('id_proof', 'scan.pdf', 600 * 1024);

        $this->expectException(BusinessRuleException::class);

        $this->createService()->processRegistration(10, 4, [], ['id_proof' => $uploadedFile]);
    }

    public function testAuditLoggerIsCalledWithCustomerActorTypeAndCorrectEntityId(): void
    {
        $field = $this->createField('full_name', true, 'text');
        $this->fieldConfigProvider->method('getFieldsForGroup')->willReturn([$field]);

        $fieldValue = $this->createFieldValueDouble();
        $this->fieldValueFactory->method('create')->willReturn($fieldValue);

        $customerId = 77;

        $this->auditLogger->expects($this->atLeastOnce())
            ->method('log')
            ->with(
                $this->equalTo('customer'),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->equalTo((string)$customerId),
                $this->anything()
            );

        $this->createService()->processRegistration(
            $customerId,
            4,
            ['full_name' => 'Jane Doe'],
            []
        );
    }

    public function testBeginsAndCommitsTransactionOnSuccessWithoutRollingBack(): void
    {
        $field = $this->createField('full_name', true, 'text');
        $this->fieldConfigProvider->method('getFieldsForGroup')->willReturn([$field]);

        $fieldValue = $this->createFieldValueDouble();
        $this->fieldValueFactory->method('create')->willReturn($fieldValue);

        $this->adapter->expects($this->once())->method('beginTransaction');
        $this->adapter->expects($this->once())->method('commit');
        $this->adapter->expects($this->never())->method('rollBack');

        $this->createService()->processRegistration(10, 4, ['full_name' => 'John Doe'], []);
    }

    public function testRollsBackTransactionAndRethrowsWhenDocumentStorageFails(): void
    {
        $field = $this->createField('id_proof', false, 'file', 'pdf', 5000);
        $this->fieldConfigProvider->method('getFieldsForGroup')->willReturn([$field]);

        $uploadedFile = $this->createUploadedFile('id_proof', 'scan.pdf', 1000);

        $this->documentStorageService->method('store')
            ->willThrowException(new \RuntimeException('storage backend unavailable'));

        $this->adapter->expects($this->once())->method('beginTransaction');
        $this->adapter->expects($this->never())->method('commit');
        $this->adapter->expects($this->once())->method('rollBack');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('storage backend unavailable');

        $this->createService()->processRegistration(10, 4, [], ['id_proof' => $uploadedFile]);
    }
}
