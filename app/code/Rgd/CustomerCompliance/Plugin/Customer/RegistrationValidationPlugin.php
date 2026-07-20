<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Plugin\Customer;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Psr\Log\LoggerInterface;
use Rgd\CustomerCompliance\Api\Data\UploadedFileInterfaceFactory;
use Rgd\CustomerCompliance\Api\RegistrationComplianceServiceInterface;

/**
 * Blocks customer account creation up front when required compliance fields/documents are
 * missing for the selected customer group, instead of only discovering the violation
 * afterward (see the gap this closes, previously documented in
 * `Observer\PersistRegistrationComplianceObserver`'s class-level PHPDoc).
 *
 * This is a "before" plugin on {@see AccountManagementInterface}: it re-derives the same
 * request-bound field/file params the Observer persists later
 * (`customercompliance_fields` / `customercompliance_files`), validates them against the
 * authoritative field list for the customer's selected group, and throws
 * {@see \Rgd\CustomerCompliance\Exception\BusinessRuleException} to abort account creation
 * entirely if anything required is missing. On success, control passes through to the real
 * `createAccount()`/`createAccountWithPasswordHash()` call unmodified, and
 * `PersistRegistrationComplianceObserver` performs the actual persistence afterward (via
 * `customer_register_success`), re-validating defensively per its own updated PHPDoc.
 */
class RegistrationValidationPlugin
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
     * Validate compliance fields before a plain account creation.
     *
     * @param AccountManagementInterface $subject
     * @param CustomerInterface $customer
     * @param string|null $password
     * @param string $redirectUrl
     * @return array
     */
    public function beforeCreateAccount(
        AccountManagementInterface $subject,
        CustomerInterface $customer,
        $password = null,
        $redirectUrl = ''
    ): array {
        $this->validate($customer);

        return [$customer, $password, $redirectUrl];
    }

    /**
     * Validate compliance fields before account creation with a password hash.
     *
     * @param AccountManagementInterface $subject
     * @param CustomerInterface $customer
     * @param string $hash
     * @param string $redirectUrl
     * @return array
     */
    public function beforeCreateAccountWithPasswordHash(
        AccountManagementInterface $subject,
        CustomerInterface $customer,
        $hash,
        $redirectUrl = ''
    ): array {
        $this->validate($customer);

        return [$customer, $hash, $redirectUrl];
    }

    /**
     * Validate the in-flight registration's compliance fields against the group.
     *
     * Throws to abort account creation if anything required is missing. If the customer
     * has no group id set yet at this point (some custom checkout/registration flows
     * assign the group afterward), there is nothing authoritative to validate against -
     * skip silently rather than guessing a fallback group, and rely on the post-creation
     * Observer/Admin-side data to catch genuinely misconfigured flows.
     *
     * @param CustomerInterface $customer
     * @return void
     * @throws \Rgd\CustomerCompliance\Exception\BusinessRuleException
     */
    private function validate(CustomerInterface $customer): void
    {
        $customerGroupId = $customer->getGroupId();
        if ($customerGroupId === null) {
            $this->logger->info(
                'Rgd_CustomerCompliance: skipping pre-creation compliance validation - '
                . 'the in-flight customer has no group id set yet.'
            );

            return;
        }

        $submittedValues = (array)$this->request->getParam(self::PARAM_FIELDS, []);
        $uploadedFiles = $this->buildUploadedFiles();

        $this->registrationComplianceService->validateSubmission(
            (int)$customerGroupId,
            $submittedValues,
            $uploadedFiles
        );
    }

    /**
     * Build UploadedFileInterface DTOs from the raw $_FILES-shape array on the request.
     *
     * Mirrors `PersistRegistrationComplianceObserver::buildUploadedFiles()` exactly, since both
     * components must agree on how the same physical request payload is interpreted.
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

            // Fixed bug (carried over from the Observer this mirrors, now fixed there too):
            // MUST be keyed by field code - RegistrationComplianceService's violation/presence
            // checks look files up via `$uploadedFiles[$code]`. A plain `[] =` append produced
            // integer keys, so every file-type field validation missed, always reporting
            // required file-upload fields as missing regardless of what was actually uploaded.
            $uploadedFiles[(string)$fieldCode] = $uploadedFile;
        }

        return $uploadedFiles;
    }
}
