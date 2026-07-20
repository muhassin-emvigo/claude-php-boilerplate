<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Observer;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use Rgd\CustomerCompliance\Api\Data\UploadedFileInterfaceFactory;
use Rgd\CustomerCompliance\Api\RegistrationComplianceServiceInterface;
use Rgd\CustomerCompliance\Exception\BusinessRuleException;
use Throwable;

/**
 * Persists submitted compliance field values/documents for a customer AFTER account creation
 * has already succeeded (fires on the `customer_register_success` event).
 *
 * IMPORTANT / GAP TO FLAG: this observer is a "happy path" persistence step only. By the time
 * `customer_register_success` fires, the customer account already exists in the database, so
 * anything this observer does (including any validation performed downstream inside
 * {@see RegistrationComplianceServiceInterface::processRegistration()}) is necessarily AFTER the
 * fact. Per the Eng spec, the AUTHORITATIVE, blocking enforcement of required compliance fields
 * is supposed to happen BEFORE account creation, in a separate component
 * (`Rgd\CustomerCompliance\Plugin\Customer\RegistrationValidationPlugin`, expected to plugin
 * around the customer account creation flow, e.g. `AccountManagementInterface::createAccount`).
 * That plugin is NOT part of this task/file set. If it does not exist elsewhere in the module,
 * there is currently NO pre-creation blocking mechanism for required-field enforcement, and
 * this observer alone cannot provide one (rethrowing here would be too late — the account is
 * already created, and rethrowing would only produce a confusing error after the fact without
 * rolling back account creation). This is flagged here deliberately rather than papered over.
 */
class PersistRegistrationComplianceObserver implements ObserverInterface
{
    private const PARAM_FIELDS = 'customercompliance_fields';
    private const PARAM_FILES = 'customercompliance_files';

    /**
     * @param RegistrationComplianceServiceInterface $registrationComplianceService
     * @param RequestInterface $request
     * @param UploadedFileInterfaceFactory $uploadedFileFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly RegistrationComplianceServiceInterface $registrationComplianceService,
        private readonly RequestInterface $request,
        private readonly UploadedFileInterfaceFactory $uploadedFileFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Persist compliance field values and documents after customer registration.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $customer = $observer->getEvent()->getCustomer();
        if ($customer === null) {
            return;
        }

        $customerId = (int)$customer->getId();
        $customerGroupId = (int)$customer->getGroupId();

        $submittedValues = (array)$this->request->getParam(self::PARAM_FIELDS, []);
        $uploadedFiles = $this->buildUploadedFiles();

        try {
            $this->registrationComplianceService->processRegistration(
                $customerId,
                $customerGroupId,
                $submittedValues,
                $uploadedFiles
            );
        } catch (BusinessRuleException $e) {
            // Too late to block account creation here; log so the gap is visible operationally.
            $this->logger->error(
                sprintf(
                    'Rgd_CustomerCompliance: business rule violation persisting compliance data '
                    . 'for customer #%d after registration: %s',
                    $customerId,
                    $e->getMessage()
                ),
                ['exception' => $e]
            );
        } catch (Throwable $e) {
            $this->logger->error(
                sprintf(
                    'Rgd_CustomerCompliance: failed to persist compliance data for customer #%d '
                    . 'after registration: %s',
                    $customerId,
                    $e->getMessage()
                ),
                ['exception' => $e]
            );
        }
    }

    /**
     * Build UploadedFileInterface DTOs from the raw $_FILES-shape array on the request.
     *
     * @return \Rgd\CustomerCompliance\Api\Data\UploadedFileInterface[]
     */
    private function buildUploadedFiles(): array
    {
        $uploadedFiles = [];

        if (!$this->request instanceof HttpRequest) {
            return $uploadedFiles;
        }

        $files = $this->request->getFiles(self::PARAM_FILES);
        if (!$files) {
            return $uploadedFiles;
        }

        // getFiles() may return a Magento\Framework\DataObject or a plain array depending on
        // how the multi-file input was submitted; normalize to an array keyed by field code.
        $filesArray = is_object($files) && method_exists($files, 'toArray') ? $files->toArray() : (array)$files;

        foreach ($filesArray as $fieldCode => $fileData) {
            if (!is_array($fileData)) {
                continue;
            }

            $errorCode = $fileData['error'] ?? null;
            if ($errorCode !== null && (int)$errorCode !== UPLOAD_ERR_OK) {
                continue;
            }

            if (empty($fileData['tmp_name'])) {
                continue;
            }

            /** @var \Rgd\CustomerCompliance\Api\Data\UploadedFileInterface $uploadedFile */
            $uploadedFile = $this->uploadedFileFactory->create();
            $uploadedFile->setFieldCode((string)$fieldCode);
            $uploadedFile->setTmpName((string)$fileData['tmp_name']);
            $uploadedFile->setOriginalName((string)($fileData['name'] ?? ''));
            $uploadedFile->setMimeType((string)($fileData['type'] ?? ''));
            $uploadedFile->setSize((int)($fileData['size'] ?? 0));

            // Fixed bug: this MUST be keyed by field code (matching
            // Controller\Account\ResubmitPost's own buildUploadedFiles(), and matching what
            // RegistrationComplianceService::collectViolations()/isValuePresent()/
            // persistFieldValues() all look up via `$uploadedFiles[$code]`). A plain `[] =`
            // append here previously produced integer keys, so every file-type field lookup by
            // code silently missed - required file-upload fields (License, ID Card, etc.) were
            // always reported as missing even when a valid file was uploaded, and optional
            // file-upload fields were never persisted at all.
            $uploadedFiles[(string)$fieldCode] = $uploadedFile;
        }

        return $uploadedFiles;
    }
}
