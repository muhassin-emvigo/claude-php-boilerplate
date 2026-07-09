<?php
declare(strict_types=1);

namespace Rgd\Inventory\Model;

use Rgd\Inventory\Model\BatchDeductionService;
use Rgd\Inventory\Model\ResourceModel\Batch as BatchResourceModel;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionRequestInterface;
use Magento\InventorySourceDeductionApi\Model\ItemToDeductInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Psr\Log\LoggerInterface;

/**
 * Source deduction coordinator — manages transaction lifecycle for batch deductions
 *
 * Responsible for:
 * 1. Resolving order_item_id from sales event and items
 * 2. Delegating to BatchDeductionService for each item (via concrete class, not interface)
 * 3. Calling proceed() within the same transaction scope
 *
 * Note: Depends on concrete BatchDeductionService (not interface) to access deductWithinTransaction(),
 * which is package-private transaction plumbing not exposed on the public @api interface.
 */
class SourceDeductionCoordinator
{
    public function __construct(
        private BatchDeductionService $batchDeductionService,
        private OrderItemRepositoryInterface $orderItemRepository,
        private SearchCriteriaBuilder $searchCriteriaBuilder,
        private LoggerInterface $logger,
        private BatchResourceModel $batchResourceModel,
    ) {}

    /**
     * Execute source deduction with batch tracking
     *
     * All items' batch deductions AND the wrapped MSI proceed() call happen inside a
     * single shared database transaction: it is opened before the loop and only
     * committed once proceed() has returned successfully. If any item's batch
     * deduction fails, or proceed() itself throws, everything (all batch deductions
     * across all items, plus whatever MSI did) is rolled back together — preventing
     * both phantom stock loss (batch ledger decremented but MSI never deducted) and
     * partial multi-item commits that would cause double-deduction on retry.
     *
     * Lock ordering / deadlock note: each item's batch row lock (FOR UPDATE in
     * BatchDeductionService::allocateWithLock()) is acquired in a deterministic
     * per-SKU order (expiry_date ASC, batch_id ASC — see that method), which
     * prevents deadlocks between two transactions both deducting the SAME SKU.
     * Items within a single request are processed in the order MSI/the sales
     * event supplied them ($sourceDeductionRequest->getItems()), which is NOT
     * necessarily the same order across two different multi-item requests that
     * share overlapping SKUs (e.g. order A = [SKU-1, SKU-2], order B = [SKU-2,
     * SKU-1]) — so a residual cross-SKU deadlock risk remains for multi-item
     * carts with overlapping SKUs in different sequences. A single globally
     * ordered lock-acquisition query across all items (e.g. one UNION'd
     * FOR UPDATE keyed by sku ASC) would close this gap but requires
     * restructuring BatchDeductionService's per-item allocation contract; not
     * undertaken here as it's a larger redesign than this pass's scope. Given
     * InnoDB's deadlock detector already resolves such conflicts by aborting one
     * transaction (which then safely retries via Magento's normal checkout retry
     * path), this is accepted as a documented residual risk rather than fixed.
     *
     * @param SourceDeductionRequestInterface $sourceDeductionRequest
     * @param callable $proceed The original SourceDeductionService::execute() method
     * @return void
     * @throws \Exception
     */
    public function executeWithBatchTracking(
        SourceDeductionRequestInterface $sourceDeductionRequest,
        callable $proceed
    ): void {
        $items = $sourceDeductionRequest->getItems();
        $sourceCode = $sourceDeductionRequest->getSourceCode();
        $salesEvent = $sourceDeductionRequest->getSalesEvent();

        $connection = $this->batchResourceModel->getConnection();

        // Resolve all order_item_ids for this request's SKUs in a single query
        // before opening the transaction/lock-holding loop below, instead of one
        // getList() call per item (N+1). Keeps the work done while locks are held
        // to a minimum (see fix #4/#6).
        $orderItemIdsBySku = $this->resolveOrderItemIdsBySku($salesEvent, $items);

        // Resolve order_id from the sales event if this is an order-bound deduction
        $orderId = null;
        if ($salesEvent->getObjectType() === \Magento\InventorySalesApi\Api\Data\SalesEventInterface::OBJECT_TYPE_ORDER) {
            $orderId = (int) $salesEvent->getObjectId();
        }

        $connection->beginTransaction();

        try {
            // For each item, look up its order_item_id from the pre-resolved map
            // and deduct from batches
            foreach ($items as $item) {
                $orderItemId = $orderItemIdsBySku[$item->getSku()] ?? null;

                // Deduct from batches within the shared transaction — no
                // commit/rollback happens here, the coordinator owns that.
                $this->batchDeductionService->deductWithinTransaction(
                    $item->getSku(),
                    $item->getQty(),
                    $salesEvent,
                    $orderItemId,
                    $sourceCode,
                    $connection,
                    $orderId
                );
            }

            // Call the original MSI deduction logic within the same transaction,
            // so our batch ledger and MSI's own source_item decrement either both
            // commit or both roll back together.
            $proceed($sourceDeductionRequest);

            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * Resolve order_item_id for every item in this request in ONE getList() call
     * (keyed by order_id + all involved SKUs), instead of one query per item.
     * Building this map happens before the transaction/lock-holding loop starts.
     *
     * @param \Magento\InventorySalesApi\Api\Data\SalesEventInterface $salesEvent
     * @param ItemToDeductInterface[] $items
     * @return array<string, int> sku => item_id
     */
    private function resolveOrderItemIdsBySku(
        \Magento\InventorySalesApi\Api\Data\SalesEventInterface $salesEvent,
        array $items
    ): array {
        // Only process order-bound deductions
        if ($salesEvent->getObjectType() !== \Magento\InventorySalesApi\Api\Data\SalesEventInterface::OBJECT_TYPE_ORDER) {
            return [];
        }

        if (empty($items)) {
            return [];
        }

        $orderId = (int) $salesEvent->getObjectId();
        $skus = array_values(array_unique(array_map(
            static fn (ItemToDeductInterface $item): string => $item->getSku(),
            $items
        )));

        $map = [];

        try {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('order_id', $orderId, 'eq')
                ->addFilter('sku', $skus, 'in')
                ->create();

            $orderItems = $this->orderItemRepository->getList($searchCriteria);

            foreach ($orderItems->getItems() as $orderItem) {
                $sku = $orderItem->getSku();

                if (isset($map[$sku])) {
                    // Multiple matches for the same SKU (should not happen per
                    // spec) — keep the first one seen and log a warning.
                    $this->logger->warning(
                        'Multiple order items found for order_id=' . $orderId . ', sku=' . $sku
                        . '; using first match (item_id=' . $map[$sku] . ')'
                    );
                    continue;
                }

                $map[$sku] = (int) $orderItem->getItemId();
            }

            return $map;
        } catch (\Exception $e) {
            $this->logger->error(
                'Error resolving order item IDs for order_id=' . $orderId
                . ', skus=' . implode(',', $skus) . ': ' . $e->getMessage()
            );
            return [];
        }
    }
}
