<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Api;

/**
 * Orchestrates compliance-field validation and persistence during customer registration.
 *
 * @api
 */
interface RegistrationComplianceServiceInterface
{
    /**
     * Validate the compliance data submitted as part of a customer registration WITHOUT
     * persisting anything. Intended to run pre-emptively, before the customer account itself
     * is created (see `Plugin\Customer\RegistrationValidationPlugin`), so a required-field
     * violation blocks account creation instead of only being noticed afterward.
     *
     * @param int $customerGroupId
     * @param array $submittedValues Submitted scalar field values, keyed by field code:
     *      `[fieldCode => value]`.
     * @param array $uploadedFiles Uploaded file fields, keyed by field code:
     *      `[fieldCode => \Psr\Http\Message\UploadedFileInterface|array]`. The array form is
     *      accepted for raw `$_FILES`-style superglobal structures.
     * @return void
     * @throws \Rgd\CustomerCompliance\Exception\BusinessRuleException If a required field is
     *      missing, a submitted value fails validation, or an uploaded file violates the
     *      configured type/size constraints.
     */
    public function validateSubmission(
        int $customerGroupId,
        array $submittedValues,
        array $uploadedFiles
    ): void;

    /**
     * Validate and persist the compliance data submitted as part of a customer registration.
     *
     * @param int $customerId
     * @param int $customerGroupId
     * @param array $submittedValues Submitted scalar field values, keyed by field code:
     *      `[fieldCode => value]`.
     * @param array $uploadedFiles Uploaded file fields, keyed by field code:
     *      `[fieldCode => \Psr\Http\Message\UploadedFileInterface|array]`. The array form is
     *      accepted for raw `$_FILES`-style superglobal structures.
     * @return void
     * @throws \Rgd\CustomerCompliance\Exception\BusinessRuleException If a required field is
     *      missing, a submitted value fails validation, or an uploaded file violates the
     *      configured type/size constraints.
     */
    public function processRegistration(
        int $customerId,
        int $customerGroupId,
        array $submittedValues,
        array $uploadedFiles
    ): void;
}
