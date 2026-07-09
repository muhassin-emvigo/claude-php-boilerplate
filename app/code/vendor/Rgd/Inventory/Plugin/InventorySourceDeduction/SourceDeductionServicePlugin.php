<?php
declare(strict_types=1);

namespace Rgd\Inventory\Plugin\InventorySourceDeduction;

use Rgd\Inventory\Model\SourceDeductionCoordinator;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionServiceInterface;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionRequestInterface;

/**
 * Around plugin on SourceDeductionServiceInterface::execute()
 *
 * Intercepts MSI deduction requests to add batch-level tracking and FEFO allocation.
 */
class SourceDeductionServicePlugin
{
    public function __construct(
        private SourceDeductionCoordinator $coordinator,
    ) {}

    /**
     * Around plugin for SourceDeductionServiceInterface::execute()
     *
     * @param SourceDeductionServiceInterface $subject
     * @param callable $proceed
     * @param SourceDeductionRequestInterface $sourceDeductionRequest
     * @return void
     */
    public function aroundExecute(
        SourceDeductionServiceInterface $subject,
        callable $proceed,
        SourceDeductionRequestInterface $sourceDeductionRequest
    ): void {
        // Delegate to coordinator, which handles batch deductions and then calls proceed()
        $this->coordinator->executeWithBatchTracking($sourceDeductionRequest, $proceed);
    }
}
