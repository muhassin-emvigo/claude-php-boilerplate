<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Intentional no-op stub.
 *
 * Per the engineering specification, this module ships WITHOUT any
 * pre-seeded group configs: administrators are expected to create
 * group configs (which customer groups are subject to compliance
 * verification, and which fields are collected for them) through the
 * admin UI after installation.
 *
 * This patch exists purely as a stable extension point / hook so that a
 * future environment-specific fixture or demo-data package can extend or
 * replace it to seed data for that specific environment (e.g. a sample
 * "General Customer" group config for a demo store), without needing to
 * introduce a brand-new patch class or module dependency.
 *
 * apply() intentionally does nothing. Do not add real seeding logic here
 * unless the business has explicitly requested default/demo data — see the
 * commented example below for the shape such seeding would take.
 */
class BackfillEligibleGroupConfigs implements DataPatchInterface
{
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
    // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedFunction
    public function apply(): void
    {
        // Intentionally left as a no-op. Ship EMPTY: administrators create
        // group configs via the admin UI.
        //
        // Example of how a demo-data or environment-specific fixture package
        // could seed a default group config, if a future requirement calls
        // for it. This is illustrative only and must NOT be uncommented
        // without explicit business sign-off:
        //
        // $connection = $this->moduleDataSetup->getConnection();
        // $groupConfigTable = $this->moduleDataSetup->getTable('rgd_customercompliance_group_config');
        //
        // $existing = $connection->fetchOne(
        //     $connection->select()
        //         ->from($groupConfigTable, ['group_config_id'])
        //         ->where('customer_group_id = ?', 0)
        // );
        //
        // if (!$existing) {
        //     $connection->insert(
        //         $groupConfigTable,
        //         [
        //             'customer_group_id' => 0,
        //             'label' => 'General Customer',
        //             'is_active' => 0,
        //         ]
        //     );
        // }
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
