<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model\Data;

use Rgd\CustomerCompliance\Api\Data\RefundResultInterface;

/**
 * The outcome of a refund attempt.
 *
 * Deliberately a plain data object (not an AbstractModel) - a refund result is a
 * transient, in-memory value returned from a strategy call, never persisted on its own.
 */
class RefundResult implements RefundResultInterface
{
    /**
     * @var string|null
     */
    private ?string $status = null;

    /**
     * @var string|null
     */
    private ?string $reference = null;

    /**
     * @var string|null
     */
    private ?string $message = null;

    /**
     * @inheritDoc
     */
    public function getStatus(): string
    {
        return (string)$this->status;
    }

    /**
     * @inheritDoc
     */
    public function setStatus(string $status): RefundResultInterface
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getReference(): ?string
    {
        return $this->reference;
    }

    /**
     * @inheritDoc
     */
    public function setReference(?string $reference): RefundResultInterface
    {
        $this->reference = $reference;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * @inheritDoc
     */
    public function setMessage(?string $message): RefundResultInterface
    {
        $this->message = $message;

        return $this;
    }
}
