<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model\Service;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Filesystem\Io\File as FilesystemIoFile;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Rgd\CustomerCompliance\Api\AuditLoggerInterface;
use Rgd\CustomerCompliance\Api\Data\FieldInterface;
use Rgd\CustomerCompliance\Api\DocumentStorageServiceInterface;
use Rgd\CustomerCompliance\Api\FieldConfigProviderInterface;
use Rgd\CustomerCompliance\Api\RegistrationComplianceServiceInterface;
use Rgd\CustomerCompliance\Exception\BusinessRuleException;
use Rgd\CustomerCompliance\Model\FieldValueFactory;
use Rgd\CustomerCompliance\Model\ResourceModel\FieldValue as FieldValueResource;

/**
 * Orchestrates compliance-field validation and persistence during customer registration.
 */
class RegistrationComplianceService implements RegistrationComplianceServiceInterface
{
    private const EMAIL_TEMPLATE_REGISTRATION_SUCCESS = 'customercompliance_registration_success';

    private const FILE_FIELD_TYPES = ['file', 'image'];

    /**
     * @param FieldConfigProviderInterface $fieldConfigProvider
     * @param DocumentStorageServiceInterface $documentStorageService
     * @param UploadedFileNormalizer $uploadedFileNormalizer
     * @param FieldValueFactory $fieldValueFactory
     * @param FieldValueResource $fieldValueResource
     * @param AuditLoggerInterface $auditLogger
     * @param ResourceConnection $resourceConnection
     * @param CustomerRepositoryInterface $customerRepository
     * @param TransportBuilder $transportBuilder
     * @param StoreManagerInterface $storeManager
     * @param DataObjectFactory $dataObjectFactory
     * @param LoggerInterface $logger
     * @param FilesystemIoFile $filesystemIoFile
     */
    public function __construct(
        private readonly FieldConfigProviderInterface $fieldConfigProvider,
        private readonly DocumentStorageServiceInterface $documentStorageService,
        private readonly UploadedFileNormalizer $uploadedFileNormalizer,
        private readonly FieldValueFactory $fieldValueFactory,
        private readonly FieldValueResource $fieldValueResource,
        private readonly AuditLoggerInterface $auditLogger,
        private readonly ResourceConnection $resourceConnection,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly TransportBuilder $transportBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly DataObjectFactory $dataObjectFactory,
        private readonly LoggerInterface $logger,
        private readonly FilesystemIoFile $filesystemIoFile
    ) {
    }

    /**
     * @inheritDoc
     */
    public function validateSubmission(
        int $customerGroupId,
        array $submittedValues,
        array $uploadedFiles
    ): void {
        // The authoritative field list always comes from the config provider - a client can
        // never be trusted to supply/limit its own required-field set.
        $fields = $this->fieldConfigProvider->getFieldsForGroup($customerGroupId);

        $violations = $this->collectViolations($fields, $submittedValues, $uploadedFiles);
        if (!empty($violations)) {
            throw new BusinessRuleException(__(implode('; ', $violations)));
        }
    }

    /**
     * @inheritDoc
     */
    public function processRegistration(
        int $customerId,
        int $customerGroupId,
        array $submittedValues,
        array $uploadedFiles
    ): void {
        // Re-validates even though `Plugin\Customer\RegistrationValidationPlugin` should already
        // have blocked account creation on a violation - this is the authoritative persistence
        // path and must not trust that the pre-creation check ran (defense in depth: the two
        // entry points can drift, or a future integration could call this service directly).
        $fields = $this->fieldConfigProvider->getFieldsForGroup($customerGroupId);

        $violations = $this->collectViolations($fields, $submittedValues, $uploadedFiles);
        if (!empty($violations)) {
            throw new BusinessRuleException(__(implode('; ', $violations)));
        }

        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();

        try {
            $this->persistFieldValues($fields, $customerId, $submittedValues, $uploadedFiles);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        $this->auditLogger->log(
            'customer',
            $customerId,
            'registration_submitted',
            'customer',
            (string)$customerId,
            null
        );

        $this->sendRegistrationSuccessEmail($customerId);
    }

    /**
     * Validate submitted values/uploaded files against the authoritative field list.
     *
     * Collects every violation rather than failing on the first one encountered.
     *
     * @param FieldInterface[] $fields
     * @param array $submittedValues
     * @param array $uploadedFiles
     * @return string[]
     */
    private function collectViolations(array $fields, array $submittedValues, array $uploadedFiles): array
    {
        $violations = [];
        $fileViolationGroups = [];

        foreach ($fields as $field) {
            $code = $field->getFieldCode();
            $isFileType = $this->isFileType($field->getFieldType());

            if ($field->isRequired() && !$this->isValuePresent($code, $isFileType, $submittedValues, $uploadedFiles)) {
                $violations[] = (string)__('The field "%1" is required.', $field->getLabel());
            }

            if ($isFileType && !empty($uploadedFiles[$code])) {
                $fileViolationGroups[] = $this->validateUploadedFile($field, $uploadedFiles[$code]);
            }
        }

        if ($fileViolationGroups !== []) {
            $violations = array_merge($violations, ...$fileViolationGroups);
        }

        return $violations;
    }

    /**
     * Determine whether a value was submitted for a given field.
     *
     * @param string $code
     * @param bool $isFileType
     * @param array $submittedValues
     * @param array $uploadedFiles
     * @return bool
     */
    private function isValuePresent(string $code, bool $isFileType, array $submittedValues, array $uploadedFiles): bool
    {
        if ($isFileType) {
            return !empty($uploadedFiles[$code]);
        }

        return isset($submittedValues[$code]) && $submittedValues[$code] !== '';
    }

    /**
     * Validate an uploaded file against its field's allowed-extension/max-size constraints.
     *
     * @param FieldInterface $field
     * @param \Psr\Http\Message\UploadedFileInterface|array $rawFile
     * @return string[]
     */
    private function validateUploadedFile(FieldInterface $field, $rawFile): array
    {
        $violations = [];
        $uploadedFile = $this->uploadedFileNormalizer->normalize($field->getFieldCode(), $rawFile);

        $allowedExtensions = $field->getAllowedExtensions();
        if ($allowedExtensions !== null && trim($allowedExtensions) !== '') {
            $allowed = array_map(
                static fn (string $ext): string => strtolower(trim($ext)),
                explode(',', $allowedExtensions)
            );
            $pathInfo = $this->filesystemIoFile->getPathInfo($uploadedFile->getOriginalName());
            $extension = strtolower((string)($pathInfo['extension'] ?? ''));

            if (!in_array($extension, $allowed, true)) {
                $violations[] = (string)__(
                    'The file for "%1" has a disallowed file extension.',
                    $field->getLabel()
                );
            }
        }

        $maxSizeKb = $field->getMaxFileSizeKb();
        if ($maxSizeKb !== null && $uploadedFile->getSize() > $maxSizeKb * 1024) {
            $violations[] = (string)__(
                'The file for "%1" exceeds the maximum allowed size of %2 KB.',
                $field->getLabel(),
                $maxSizeKb
            );
        }

        return $violations;
    }

    /**
     * Persist submitted field values/uploaded documents.
     *
     * Must run inside the caller's DB transaction. Note: if
     * `DocumentStorageServiceInterface::store()` fails partway through this loop,
     * cleanup of the physical file it may have already written for the *current* field is
     * the storage service's own responsibility (see DocumentStorageService::store()) - this
     * method only needs to roll back the field_value rows via the DB transaction.
     *
     * @param FieldInterface[] $fields
     * @param int $customerId
     * @param array $submittedValues
     * @param array $uploadedFiles
     * @return void
     */
    private function persistFieldValues(
        array $fields,
        int $customerId,
        array $submittedValues,
        array $uploadedFiles
    ): void {
        foreach ($fields as $field) {
            $code = $field->getFieldCode();

            if ($this->isFileType($field->getFieldType())) {
                if (empty($uploadedFiles[$code])) {
                    continue;
                }

                $uploadedFile = $this->uploadedFileNormalizer->normalize($code, $uploadedFiles[$code]);
                $document = $this->documentStorageService->store(
                    $customerId,
                    $field->getFieldId(),
                    null,
                    $uploadedFile
                );

                $fieldValue = $this->fieldValueFactory->create();
                $fieldValue->setCustomerId($customerId)
                    ->setFieldId($field->getFieldId())
                    ->setValue(null)
                    ->setDocumentId($document->getDocumentId());
                $this->fieldValueResource->save($fieldValue);

                continue;
            }

            if (array_key_exists($code, $submittedValues)) {
                $fieldValue = $this->fieldValueFactory->create();
                $fieldValue->setCustomerId($customerId)
                    ->setFieldId($field->getFieldId())
                    ->setValue((string)$submittedValues[$code])
                    ->setDocumentId(null);
                $this->fieldValueResource->save($fieldValue);
            }
        }
    }

    /**
     * Whether the given field type represents a file upload.
     *
     * @param string $fieldType
     * @return bool
     */
    private function isFileType(string $fieldType): bool
    {
        return in_array($fieldType, self::FILE_FIELD_TYPES, true);
    }

    /**
     * Best-effort registration-success notification.
     *
     * A notification failure must never fail a registration that has already committed
     * to the database.
     *
     * @param int $customerId
     * @return void
     */
    private function sendRegistrationSuccessEmail(int $customerId): void
    {
        try {
            $customer = $this->customerRepository->getById($customerId);
            $storeId = (int)$this->storeManager->getStore()->getId();

            $transport = $this->transportBuilder
                ->setTemplateIdentifier(self::EMAIL_TEMPLATE_REGISTRATION_SUCCESS)
                ->setTemplateOptions([
                    'area' => Area::AREA_FRONTEND,
                    'store' => $storeId,
                ])
                ->setTemplateVars([
                    'customer_id' => $customerId,
                    'customer' => $this->dataObjectFactory->create([
                        'data' => ['name' => trim($customer->getFirstname() . ' ' . $customer->getLastname())],
                    ]),
                    'store' => $this->storeManager->getStore(),
                ])
                ->setFrom('general')
                ->addTo(
                    (string)$customer->getEmail(),
                    trim($customer->getFirstname() . ' ' . $customer->getLastname())
                )
                ->getTransport();

            $transport->sendMessage();
        } catch (\Throwable $e) {
            $this->logger->error(
                'Failed to send compliance registration-success email: ' . $e->getMessage(),
                ['exception' => $e, 'customer_id' => $customerId]
            );
        }
    }
}
