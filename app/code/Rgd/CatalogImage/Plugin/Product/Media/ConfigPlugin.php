<?php

declare(strict_types=1);

namespace Rgd\CatalogImage\Plugin\Product\Media;

use Magento\Catalog\Model\Product\Media\Config;

/**
 * Redirects the admin product-gallery temporary upload folder away from
 * the shared "catalog/product" path used for permanent, already-saved
 * product images, without touching the permanent storage path itself.
 */
class ConfigPlugin
{
    private const ORIGINAL_TMP_SEGMENT = 'tmp/catalog/product';
    private const REPLACEMENT_TMP_SEGMENT = 'tmp/catalog/product_new';

    /**
     * Redirect the temporary media path to the replacement segment.
     *
     * @param Config $subject
     * @param string $result
     * @return string
     */
    public function afterGetBaseTmpMediaPath(Config $subject, string $result): string
    {
        return $this->replaceTmpSegment($result);
    }

    /**
     * Redirect the temporary media URL to the replacement segment.
     *
     * @param Config $subject
     * @param string $result
     * @return string
     */
    public function afterGetBaseTmpMediaUrl(Config $subject, string $result): string
    {
        return $this->replaceTmpSegment($result);
    }

    /**
     * Redirect the temporary media short URL to the replacement segment.
     *
     * @param Config $subject
     * @param string $result
     * @param string $file
     * @return string
     */
    public function afterGetTmpMediaShortUrl(Config $subject, string $result, string $file): string
    {
        return $this->replaceTmpSegment($result);
    }

    /**
     * Replace the original tmp path segment with the replacement segment.
     *
     * @param string $path
     * @return string
     */
    private function replaceTmpSegment(string $path): string
    {
        return str_replace(self::ORIGINAL_TMP_SEGMENT, self::REPLACEMENT_TMP_SEGMENT, $path);
    }
}
