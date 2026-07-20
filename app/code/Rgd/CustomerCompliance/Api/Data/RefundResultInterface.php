<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Api\Data;

/**
 * The outcome of a refund attempt.
 *
 * @api
 */
interface RefundResultInterface
{
    /**
     * The refund completed successfully.
     */
    public const STATUS_COMPLETED = 'completed';

    /**
     * The refund attempt failed.
     */
    public const STATUS_FAILED = 'failed';

    /**
     * The refund could not be completed automatically and requires manual handling.
     */
    public const STATUS_MANUAL_FALLBACK = 'manual_fallback';

    /**
     * Get the refund status. One of the STATUS_* constants.
     *
     * @return string
     */
    public function getStatus(): string;

    /**
     * Set the refund status. One of the STATUS_* constants.
     *
     * @param string $status
     * @return $this
     */
    public function setStatus(string $status): self;

    /**
     * Get the refund gateway/transaction reference.
     *
     * @return string|null
     */
    public function getReference(): ?string;

    /**
     * Set the refund gateway/transaction reference.
     *
     * @param string|null $reference
     * @return $this
     */
    public function setReference(?string $reference): self;

    /**
     * Get a human-readable message describing the result.
     *
     * @return string|null
     */
    public function getMessage(): ?string;

    /**
     * Set a human-readable message describing the result.
     *
     * @param string|null $message
     * @return $this
     */
    public function setMessage(?string $message): self;
}
