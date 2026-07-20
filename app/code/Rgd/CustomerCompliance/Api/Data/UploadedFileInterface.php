<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Api\Data;

/**
 * A normalized DTO wrapping a single uploaded file, passed to the document storage service.
 *
 * @api
 */
interface UploadedFileInterface
{
    /**
     * Get the compliance field code this file was uploaded for.
     *
     * @return string
     */
    public function getFieldCode(): string;

    /**
     * Set the compliance field code this file was uploaded for.
     *
     * @param string $fieldCode
     * @return $this
     */
    public function setFieldCode(string $fieldCode): self;

    /**
     * Get the server-side temporary file path.
     *
     * @return string
     */
    public function getTmpName(): string;

    /**
     * Set the server-side temporary file path.
     *
     * @param string $tmpName
     * @return $this
     */
    public function setTmpName(string $tmpName): self;

    /**
     * Get the original client-side file name.
     *
     * @return string
     */
    public function getOriginalName(): string;

    /**
     * Set the original client-side file name.
     *
     * @param string $originalName
     * @return $this
     */
    public function setOriginalName(string $originalName): self;

    /**
     * Get the file's MIME type.
     *
     * @return string
     */
    public function getMimeType(): string;

    /**
     * Set the file's MIME type.
     *
     * @param string $mimeType
     * @return $this
     */
    public function setMimeType(string $mimeType): self;

    /**
     * Get the file size, in bytes.
     *
     * @return int
     */
    public function getSize(): int;

    /**
     * Set the file size, in bytes.
     *
     * @param int $size
     * @return $this
     */
    public function setSize(int $size): self;
}
