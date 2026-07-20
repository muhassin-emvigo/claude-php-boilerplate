<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Api;

/**
 * Handles resubmission of compliance documents against a rejected order.
 *
 * @api
 */
interface DocumentResubmissionServiceInterface
{
    /**
     * Resubmit compliance documents for a previously rejected order.
     *
     * @param int $orderId
     * @param array $uploadedFiles Uploaded file fields, keyed by field code:
     *      `[fieldCode => \Psr\Http\Message\UploadedFileInterface|array]`.
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Rgd\CustomerCompliance\Exception\BusinessRuleException
     */
    public function resubmit(int $orderId, array $uploadedFiles): void;
}
