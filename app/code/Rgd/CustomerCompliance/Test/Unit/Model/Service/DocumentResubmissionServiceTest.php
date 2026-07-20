<?php

// NOTE: Written in parallel with Model\Service\DocumentResubmissionService. The task brief calls
// for a StateException on an invalid (non-rejected) approval status, though the sibling
// DocumentResubmissionServiceInterface PHPDoc only documents @throws NoSuchEntityException and
// BusinessRuleException; this is flagged here rather than silently reconciled. The exact
// mechanism for resolving a field code to a field id (needed to call
// DocumentStorageServiceInterface::store()'s $fieldId argument) is also not fully specified by
// the three mocked collaborators named in the spec, so that argument is asserted loosely
// (isType('int')) rather than to an exact value. Re-run and adjust against the actual
// implementation during Build-stage integration.

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Test\Unit\Model\Service;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\State\StateException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rgd\CustomerCompliance\Api\AuditLoggerInterface;
use Rgd\CustomerCompliance\Api\Data\DocumentInterface;
use Rgd\CustomerCompliance\Api\Data\FieldInterface;
use Rgd\CustomerCompliance\Api\Data\FieldSearchResultsInterface;
use Rgd\CustomerCompliance\Api\Data\OrderApprovalInterface;
use Rgd\CustomerCompliance\Api\Data\UploadedFileInterface;
use Rgd\CustomerCompliance\Api\DocumentStorageServiceInterface;
use Rgd\CustomerCompliance\Api\FieldRepositoryInterface;
use Rgd\CustomerCompliance\Model\OrderApprovalRepository;
use Rgd\CustomerCompliance\Model\Service\DocumentResubmissionService;
use Rgd\CustomerCompliance\Model\Service\UploadedFileNormalizer;

/**
 * @covers \Rgd\CustomerCompliance\Model\Service\DocumentResubmissionService
 */
class DocumentResubmissionServiceTest extends TestCase
{
    // NOTE (fixed): the real constructor type-hints the CONCRETE
    // Rgd\CustomerCompliance\Model\OrderApprovalRepository class, not
    // OrderApprovalRepositoryInterface - a mock of the interface alone does not satisfy that
    // parameter type. Mocking the concrete class instead (still fully mockable, since
    // createMock() on a non-final class works the same way) fixes the constructor mismatch.
    private OrderApprovalRepository&MockObject $orderApprovalRepository;
    private DocumentStorageServiceInterface&MockObject $documentStorageService;
    private AuditLoggerInterface&MockObject $auditLogger;
    private FieldRepositoryInterface&MockObject $fieldRepository;
    private SearchCriteriaBuilder&MockObject $searchCriteriaBuilder;
    private UploadedFileNormalizer&MockObject $uploadedFileNormalizer;

    protected function setUp(): void
    {
        $this->orderApprovalRepository = $this->createMock(OrderApprovalRepository::class);
        $this->documentStorageService = $this->createMock(DocumentStorageServiceInterface::class);
        $this->auditLogger = $this->createMock(AuditLoggerInterface::class);
        $this->fieldRepository = $this->createMock(FieldRepositoryInterface::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->uploadedFileNormalizer = $this->createMock(UploadedFileNormalizer::class);

        $this->searchCriteriaBuilder->method('addFilter')->willReturnSelf();
        $this->searchCriteriaBuilder->method('create')
            ->willReturn($this->createMock(SearchCriteriaInterface::class));

        // getFieldByCode() resolves a Field by code via fieldRepository->getList(); the exact
        // field_id doesn't matter for these tests (DocumentStorageService::store() is only
        // asserted to receive *an* int), so always resolve to the same single field regardless
        // of which code was searched for.
        $field = $this->createMock(FieldInterface::class);
        $field->method('getFieldId')->willReturn(1);
        $searchResults = $this->createMock(FieldSearchResultsInterface::class);
        $searchResults->method('getItems')->willReturn([$field]);
        $this->fieldRepository->method('getList')->willReturn($searchResults);

        // normalize() is a pass-through for this module's own UploadedFileInterface DTOs (see
        // UploadedFileNormalizer's bug-fix PHPDoc); every uploaded-file double created below
        // already implements that DTO interface.
        $this->uploadedFileNormalizer->method('normalize')->willReturnArgument(1);
    }

    private function createService(): DocumentResubmissionService
    {
        return new DocumentResubmissionService(
            $this->orderApprovalRepository,
            $this->documentStorageService,
            $this->auditLogger,
            $this->fieldRepository,
            $this->searchCriteriaBuilder,
            $this->uploadedFileNormalizer
        );
    }

    private function createApproval(string $status, int $resubmissionCount = 0): OrderApprovalInterface&MockObject
    {
        $approval = $this->createMock(OrderApprovalInterface::class);
        $approval->method('getStatus')->willReturn($status);
        $approval->method('getOrderId')->willReturn(100);
        $approval->method('getCustomerId')->willReturn(55);
        $approval->method('getResubmissionCount')->willReturn($resubmissionCount);
        $approval->method('setStatus')->willReturnSelf();
        $approval->method('setResubmissionCount')->willReturnSelf();

        return $approval;
    }

    private function createUploadedFile(string $code): UploadedFileInterface&MockObject
    {
        $file = $this->createMock(UploadedFileInterface::class);
        $file->method('getFieldCode')->willReturn($code);
        $file->method('getTmpName')->willReturn('/tmp/php' . $code);
        $file->method('getOriginalName')->willReturn($code . '.pdf');
        $file->method('getMimeType')->willReturn('application/pdf');
        $file->method('getSize')->willReturn(1024);

        return $file;
    }

    public function testThrowsStateExceptionWhenApprovalIsNotRejected(): void
    {
        $approval = $this->createApproval(OrderApprovalInterface::STATUS_PENDING_VERIFICATION);
        $this->orderApprovalRepository->method('getByOrderId')->with(100)->willReturn($approval);

        $this->documentStorageService->expects($this->never())->method('store');

        $this->expectException(StateException::class);

        $this->createService()->resubmit(100, ['id_proof' => $this->createUploadedFile('id_proof')]);
    }

    public function testResubmitStoresEachFileResetsStatusAndIncrementsResubmissionCount(): void
    {
        $approval = $this->createApproval(OrderApprovalInterface::STATUS_REJECTED, 2);
        $this->orderApprovalRepository->method('getByOrderId')->with(100)->willReturn($approval);

        $document = $this->createMock(DocumentInterface::class);
        $this->documentStorageService->expects($this->exactly(2))->method('store')
            ->with(55, $this->isType('int'), 100, $this->isInstanceOf(UploadedFileInterface::class))
            ->willReturn($document);

        $approval->expects($this->once())->method('setStatus')
            ->with(OrderApprovalInterface::STATUS_PENDING_VERIFICATION)
            ->willReturnSelf();

        // Previous resubmission_count was 2; expect it to be incremented to 3.
        $approval->expects($this->once())->method('setResubmissionCount')->with(3)->willReturnSelf();

        $this->orderApprovalRepository->expects($this->once())->method('save')->with($approval)
            ->willReturn($approval);

        $uploadedFiles = [
            'id_proof' => $this->createUploadedFile('id_proof'),
            'address_proof' => $this->createUploadedFile('address_proof'),
        ];

        $this->createService()->resubmit(100, $uploadedFiles);
    }

    public function testResubmitWithSingleFileStoresExactlyOnce(): void
    {
        $approval = $this->createApproval(OrderApprovalInterface::STATUS_REJECTED, 0);
        $this->orderApprovalRepository->method('getByOrderId')->with(100)->willReturn($approval);

        $document = $this->createMock(DocumentInterface::class);
        $this->documentStorageService->expects($this->once())->method('store')->willReturn($document);

        $approval->expects($this->once())->method('setResubmissionCount')->with(1)->willReturnSelf();
        $this->orderApprovalRepository->expects($this->once())->method('save')->willReturn($approval);

        $this->createService()->resubmit(100, ['id_proof' => $this->createUploadedFile('id_proof')]);
    }
}
