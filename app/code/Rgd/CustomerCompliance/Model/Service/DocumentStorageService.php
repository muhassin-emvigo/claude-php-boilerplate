<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model\Service;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Config\ConfigOptionsListConstants;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Filesystem\Io\File as FilesystemIoFile;
use Magento\Framework\Math\Random;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;
use Rgd\CustomerCompliance\Api\AuditLoggerInterface;
use Rgd\CustomerCompliance\Api\Data;
use Rgd\CustomerCompliance\Api\Data\UploadedFileInterface;
use Rgd\CustomerCompliance\Api\DocumentStorageServiceInterface;
use Rgd\CustomerCompliance\Exception\BusinessRuleException;
use Rgd\CustomerCompliance\Model\DocumentFactory;
use Rgd\CustomerCompliance\Model\DocumentRepository;
use Rgd\CustomerCompliance\Model\ResourceModel\Document as DocumentResource;

/**
 * Handles physical storage and secure retrieval of uploaded compliance documents.
 */
class DocumentStorageService implements DocumentStorageServiceInterface
{
    private const STORAGE_ROOT = 'customercompliance';

    private const DOWNLOAD_ROUTE = 'customercompliance/document/download';

    /**
     * Lightweight signed-URL lifetime, in seconds.
     */
    private const SIGNED_URL_TTL_SECONDS = 900;

    /**
     * @param Filesystem $filesystem
     * @param DocumentFactory $documentFactory
     * @param DocumentResource $documentResource
     * @param DocumentRepository $documentRepository
     * @param Random $random
     * @param AuditLoggerInterface $auditLogger
     * @param UrlInterface $urlBuilder
     * @param FileFactory $fileFactory
     * @param DeploymentConfig $deploymentConfig
     * @param LoggerInterface $logger
     * @param FilesystemIoFile $filesystemIoFile
     */
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly DocumentFactory $documentFactory,
        private readonly DocumentResource $documentResource,
        private readonly DocumentRepository $documentRepository,
        private readonly Random $random,
        private readonly AuditLoggerInterface $auditLogger,
        private readonly UrlInterface $urlBuilder,
        private readonly FileFactory $fileFactory,
        private readonly DeploymentConfig $deploymentConfig,
        private readonly LoggerInterface $logger,
        private readonly FilesystemIoFile $filesystemIoFile
    ) {
    }

    /**
     * @inheritDoc
     */
    public function store(
        int $customerId,
        int $fieldId,
        ?int $orderId,
        UploadedFileInterface $file
    ): Data\DocumentInterface {
        $varDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $relativeDir = $this->buildStorageDirectory($customerId, $fieldId);
        $varDirectory->create($relativeDir);

        $extension = $this->extractExtension($file->getOriginalName());
        $version = $this->resolveNextVersion($customerId, $fieldId, $orderId);
        $relativePath = $relativeDir . '/' . $version . '_' . $this->random->getRandomString(32) . '.' . $extension;

        $checksum = hash_file('sha256', $file->getTmpName());
        if ($checksum === false) {
            throw new BusinessRuleException(__('Unable to read the uploaded file.'));
        }

        try {
            $varDirectory->getDriver()->copy($file->getTmpName(), $varDirectory->getAbsolutePath($relativePath));
        } catch (\Throwable $e) {
            $this->logger->error('Failed to store compliance document file: ' . $e->getMessage(), ['exception' => $e]);
            throw new CouldNotSaveException(__('Could not store the uploaded document.'), $e);
        }

        try {
            $this->markPriorDocumentsNotCurrent($customerId, $fieldId, $orderId);

            /** @var Data\DocumentInterface $document */
            $document = $this->documentFactory->create();
            $document->setCustomerId($customerId)
                ->setFieldId($fieldId)
                ->setOrderId($orderId)
                ->setFileName($file->getOriginalName())
                ->setFilePath($relativePath)
                ->setContentType($file->getMimeType())
                ->setFileSize($file->getSize())
                ->setVersion($version)
                ->setCurrent(true)
                ->setChecksum($checksum);

            $document = $this->documentRepository->save($document);
        } catch (\Throwable $e) {
            // The DB write failed after the physical file was already written: clean up the
            // now-orphaned file here, since nothing else owns/tracks it at this point. This
            // is the failure-path cleanup referenced by callers such as
            // RegistrationComplianceService, which only need to roll back their own DB work.
            $this->cleanUpOrphanedFile($varDirectory, $relativePath);

            throw $e;
        }

        $this->auditLogger->log(
            'customer',
            $customerId,
            'document_uploaded',
            'document',
            (string)$document->getDocumentId(),
            null
        );

        return $document;
    }

    /**
     * @inheritDoc
     */
    public function getSecureUrl(int $documentId): string
    {
        // Propagates NoSuchEntityException if the document does not exist, which the
        // webapi/controller layer maps to a 404.
        $this->documentRepository->getById($documentId);

        $expiresAt = time() + self::SIGNED_URL_TTL_SECONDS;
        $signature = $this->sign($documentId, $expiresAt);

        // NOTE: this is a lightweight timestamp+HMAC signature intended only to deter casual
        // URL guessing/reuse past expiry. It is not a substitute for a full expiring-URL
        // infrastructure (e.g. one-time tokens, revocation) - flagged as a possible hardening
        // follow-up per the Eng spec, not a blocking concern for this iteration.
        return $this->urlBuilder->getUrl(self::DOWNLOAD_ROUTE, [
            'id' => $documentId,
            'expires' => $expiresAt,
            'sig' => $signature,
        ]);
    }

    /**
     * @inheritDoc
     *
     * NOTE FOR CODE REVIEW: the interface declares a `void` return, which is an awkward fit
     * for a file-streaming operation - there is no way for this method to hand the caller a
     * response object to further customize/send. The pragmatic implementation here uses
     * Magento's `Framework\App\Response\Http\FileFactory`, which is deliberately constructed
     * around a *shared* `Response\Http` instance: calling `create()` populates that shared
     * response's headers/content as a side effect, and the framework's front controller is
     * what ultimately calls `sendResponse()` on it. That happens to make a `void`-returning
     * implementation workable, but it is an implicit, easy-to-miss coupling. A future
     * iteration should have the controller layer own response construction directly (calling
     * `FileFactory::create()` itself and returning its `ResponseInterface`) rather than
     * routing it through a service method that can't express that return value.
     */
    public function streamDownload(int $documentId): void
    {
        $document = $this->documentRepository->getById($documentId);

        $varDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        if (!$varDirectory->isFile($document->getFilePath())) {
            throw new LocalizedException(__('The requested document file could not be found on disk.'));
        }

        // This method intentionally does not audit-log the download: its signature carries
        // no caller/actor identity (customer vs. admin, which id), so fabricating one here
        // would produce an inaccurate audit trail. The calling controller, which does know
        // who is downloading, is responsible for its own `AuditLoggerInterface::log()` call.
        $this->fileFactory->create(
            $document->getFileName(),
            ['type' => 'filename', 'value' => $document->getFilePath()],
            DirectoryList::VAR_DIR,
            $document->getContentType()
        );
    }

    /**
     * Build the relative storage directory for a customer/field pair.
     *
     * @param int $customerId
     * @param int $fieldId
     * @return string
     */
    private function buildStorageDirectory(int $customerId, int $fieldId): string
    {
        return self::STORAGE_ROOT . '/' . $customerId . '/' . $fieldId;
    }

    /**
     * Safely derive a lowercased, alphanumeric-only extension from an original file name.
     *
     * @param string $originalName
     * @return string
     */
    private function extractExtension(string $originalName): string
    {
        $pathInfo = $this->filesystemIoFile->getPathInfo($originalName);
        $extension = strtolower((string)($pathInfo['extension'] ?? ''));
        $extension = (string)preg_replace('/[^a-z0-9]/', '', $extension);

        return $extension !== '' ? $extension : 'bin';
    }

    /**
     * Resolve the next document version number for a customer/field(/order) tuple.
     *
     * @param int $customerId
     * @param int $fieldId
     * @param int|null $orderId
     * @return int
     */
    private function resolveNextVersion(int $customerId, int $fieldId, ?int $orderId): int
    {
        $connection = $this->documentResource->getConnection();
        $select = $connection->select()
            ->from($this->documentResource->getMainTable(), ['max_version' => new Expression('MAX(version)')])
            ->where('customer_id = ?', $customerId)
            ->where('field_id = ?', $fieldId);

        $select->where($orderId !== null ? $connection->quoteInto('order_id = ?', $orderId) : 'order_id IS NULL');

        $maxVersion = $connection->fetchOne($select);

        return $maxVersion !== null && $maxVersion !== false ? ((int)$maxVersion + 1) : 1;
    }

    /**
     * Mark all prior documents for a customer/field(/order) tuple as no longer current.
     *
     * Uses a parameterized `$connection->update()` call (values escaped via `quoteInto()`),
     * not raw string concatenation of user input.
     *
     * @param int $customerId
     * @param int $fieldId
     * @param int|null $orderId
     * @return void
     */
    private function markPriorDocumentsNotCurrent(int $customerId, int $fieldId, ?int $orderId): void
    {
        $connection = $this->documentResource->getConnection();

        $conditions = [
            $connection->quoteInto('customer_id = ?', $customerId),
            $connection->quoteInto('field_id = ?', $fieldId),
            $connection->quoteInto('is_current = ?', 1),
            $orderId !== null ? $connection->quoteInto('order_id = ?', $orderId) : 'order_id IS NULL',
        ];

        $connection->update(
            $this->documentResource->getMainTable(),
            ['is_current' => 0],
            implode(' AND ', $conditions)
        );
    }

    /**
     * Delete an orphaned physical file left behind by a failed DB write.
     *
     * @param WriteInterface $varDirectory
     * @param string $relativePath
     * @return void
     */
    private function cleanUpOrphanedFile(WriteInterface $varDirectory, string $relativePath): void
    {
        try {
            if ($varDirectory->isExist($relativePath)) {
                $varDirectory->delete($relativePath);
            }
        } catch (\Throwable $cleanupException) {
            $this->logger->error(
                'Failed to clean up orphaned compliance document file "' . $relativePath . '": '
                . $cleanupException->getMessage(),
                ['exception' => $cleanupException]
            );
        }
    }

    /**
     * Compute the lightweight HMAC signature for a secure download URL.
     *
     * @param int $documentId
     * @param int $expiresAt
     * @return string
     */
    private function sign(int $documentId, int $expiresAt): string
    {
        $secret = (string)$this->deploymentConfig->get(ConfigOptionsListConstants::CONFIG_PATH_CRYPT_KEY);

        return hash_hmac('sha256', $documentId . '|' . $expiresAt, $secret);
    }
}
