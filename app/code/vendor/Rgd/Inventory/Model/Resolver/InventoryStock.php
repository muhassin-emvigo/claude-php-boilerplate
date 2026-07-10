<?php
declare(strict_types=1);

namespace Rgd\Inventory\Model\Resolver;

use Rgd\Inventory\Api\FefoBatchSelectorInterface;
use Rgd\Inventory\Api\Data\BatchInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Resolves rgdInventoryStock: FEFO batch inventory and available-to-sell
 * quantity for a SKU. Public/unauthenticated, same as standard stock queries -
 * customers need to see stock before logging in.
 */
class InventoryStock implements ResolverInterface
{
    public function __construct(
        private FefoBatchSelectorInterface $fefoBatchSelector,
    ) {}

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $sku = trim((string) ($args['sku'] ?? ''));
        if ($sku === '') {
            throw new GraphQlInputException(__('"sku" is required.'));
        }

        $sourceCode = (string) ($args['sourceCode'] ?? 'default');

        $batches = $this->fefoBatchSelector->getAvailableBatches($sku, $sourceCode);

        return [
            'sku' => $sku,
            'available_qty' => (float) array_sum(array_map(
                static fn (BatchInterface $batch): float => $batch->getRemainingQty(),
                $batches
            )),
            'batches' => array_map(
                static fn (BatchInterface $batch): array => [
                    'batch_number' => $batch->getBatchNumber(),
                    'expiry_date' => $batch->getExpiryDate(),
                    'available_qty' => $batch->getRemainingQty(),
                    'received_at' => $batch->getCreatedAt(),
                ],
                $batches
            ),
        ];
    }
}
