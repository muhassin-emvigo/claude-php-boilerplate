<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Idempotently seeds the human-readable label row for the "pending_verification"
 * order status/state pair.
 *
 * Order status/state registration is normally handled declaratively via
 * etc/sales.xml (maintained separately for this module). This patch exists as a
 * defensive fallback: on some Magento 2.4.x versions/setups the declarative
 * status registration does not reliably populate the `sales_order_status` and
 * `sales_order_status_state` tables, so this patch guards for that condition
 * and inserts the rows only if they are missing.
 */
class InitSalesOrderStatus implements DataPatchInterface
{
    private const STATUS = 'pending_verification';
    private const LABEL = 'Pending Verification';
    private const STATE = 'processing';

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * @inheritDoc
     */
    public function apply(): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $statusTable = $this->moduleDataSetup->getTable('sales_order_status');
        $statusStateTable = $this->moduleDataSetup->getTable('sales_order_status_state');

        $existingStatus = $connection->fetchOne(
            $connection->select()
                ->from($statusTable, ['status'])
                ->where('status = ?', self::STATUS)
        );

        if (!$existingStatus) {
            $connection->insert(
                $statusTable,
                [
                    'status' => self::STATUS,
                    'label' => self::LABEL,
                ]
            );
        }

        $existingState = $connection->fetchOne(
            $connection->select()
                ->from($statusStateTable, ['status'])
                ->where('status = ?', self::STATUS)
                ->where('state = ?', self::STATE)
        );

        if (!$existingState) {
            $connection->insert(
                $statusStateTable,
                [
                    'status' => self::STATUS,
                    'state' => self::STATE,
                    'is_default' => 0,
                ]
            );
        }
    }

    /**
     * Removes the specific rows seeded by this patch, identified by their
     * known status/state keys. Not part of DataPatchInterface, but kept as a
     * defensive convenience method in case the patch applier or a future
     * maintenance script invokes it explicitly.
     */
    public function revert(): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $statusTable = $this->moduleDataSetup->getTable('sales_order_status');
        $statusStateTable = $this->moduleDataSetup->getTable('sales_order_status_state');

        $connection->delete(
            $statusStateTable,
            ['status = ?' => self::STATUS, 'state = ?' => self::STATE]
        );

        $connection->delete(
            $statusTable,
            ['status = ?' => self::STATUS]
        );
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
