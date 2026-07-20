<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model\Service;

use Psr\Http\Message\UploadedFileInterface as PsrUploadedFileInterface;
use Rgd\CustomerCompliance\Api\Data\UploadedFileInterface;
use Rgd\CustomerCompliance\Api\Data\UploadedFileInterfaceFactory;

/**
 * Normalizes the various raw uploaded-file representations accepted by the compliance
 * services (a PSR-7 UploadedFileInterface, or a `$_FILES`-style array) into this module's
 * own {@see UploadedFileInterface} DTO.
 *
 * Extracted into its own injectable collaborator (rather than duplicated private methods
 * on each consuming service) so the normalization/edge-case handling has a single,
 * independently unit-testable home.
 */
class UploadedFileNormalizer
{
    /**
     * @param UploadedFileInterfaceFactory $uploadedFileFactory
     */
    public function __construct(
        private readonly UploadedFileInterfaceFactory $uploadedFileFactory
    ) {
    }

    /**
     * Normalize a raw uploaded file into the module's UploadedFileInterface DTO.
     *
     * Bug fix: every real call site in this module (`Observer\PersistRegistrationComplianceObserver`,
     * `Plugin\Customer\RegistrationValidationPlugin`, `Controller\Account\ResubmitPost`) already
     * builds a `Rgd\CustomerCompliance\Api\Data\UploadedFileInterface` DTO itself (from the raw
     * `$_FILES`-shape request data) before handing it to the services that call this method. This
     * method previously only recognized a PSR-7 `UploadedFileInterface` or a raw array, so every
     * real invocation fell through to the `InvalidArgumentException` below - registration and
     * resubmission file uploads always failed. This DTO case is now checked first and simply
     * passed through unchanged (it's already normalized), so the module's own type is accepted
     * alongside the two "genuinely raw" shapes this class was originally written to handle (kept
     * for forward-compatibility, e.g. a future direct webapi.xml file-upload endpoint).
     *
     * @param string $fieldCode
     * @param UploadedFileInterface|PsrUploadedFileInterface|array $rawFile
     * @return UploadedFileInterface
     */
    public function normalize(string $fieldCode, $rawFile): UploadedFileInterface
    {
        if ($rawFile instanceof UploadedFileInterface) {
            return $rawFile;
        }

        if ($rawFile instanceof PsrUploadedFileInterface) {
            return $this->normalizePsrFile($fieldCode, $rawFile);
        }

        if (is_array($rawFile)) {
            return $this->normalizeArrayFile($fieldCode, $rawFile);
        }

        throw new \InvalidArgumentException(
            sprintf('Unsupported uploaded file representation for field "%s".', $fieldCode)
        );
    }

    /**
     * Normalize a PSR-7 uploaded file.
     *
     * Note: PSR-7's UploadedFileInterface does not guarantee an accessible temporary file
     * path; this relies on the underlying stream's "uri" metadata, which is populated for
     * stream wrappers backed by a real file on disk (the common case for PHP's built-in
     * upload handling). Flag for review if a different PSR-7 implementation is introduced.
     *
     * @param string $fieldCode
     * @param PsrUploadedFileInterface $file
     * @return UploadedFileInterface
     */
    private function normalizePsrFile(string $fieldCode, PsrUploadedFileInterface $file): UploadedFileInterface
    {
        $tmpName = (string)($file->getStream()->getMetadata('uri') ?? '');

        /** @var UploadedFileInterface $dto */
        $dto = $this->uploadedFileFactory->create();
        $dto->setFieldCode($fieldCode)
            ->setTmpName($tmpName)
            ->setOriginalName((string)$file->getClientFilename())
            ->setMimeType((string)($file->getClientMediaType() ?? 'application/octet-stream'))
            ->setSize((int)$file->getSize());

        return $dto;
    }

    /**
     * Normalize a `$_FILES`-style array (`tmp_name`, `name`, `type`, `size`).
     *
     * @param string $fieldCode
     * @param array $rawFile
     * @return UploadedFileInterface
     */
    private function normalizeArrayFile(string $fieldCode, array $rawFile): UploadedFileInterface
    {
        /** @var UploadedFileInterface $dto */
        $dto = $this->uploadedFileFactory->create();
        $dto->setFieldCode($fieldCode)
            ->setTmpName((string)($rawFile['tmp_name'] ?? ''))
            ->setOriginalName((string)($rawFile['name'] ?? ''))
            ->setMimeType((string)($rawFile['type'] ?? 'application/octet-stream'))
            ->setSize((int)($rawFile['size'] ?? 0));

        return $dto;
    }
}
