<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Controller\Adminhtml\Approval;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\State\StateException;
use Magento\Ui\Component\MassAction\Filter;
use Rgd\CustomerCompliance\Api\OrderApprovalServiceInterface;
use Rgd\CustomerCompliance\Model\ResourceModel\OrderApproval\CollectionFactory;
use Throwable;

/**
 * Bulk-approves the selected rows on the Order Approvals ("Pending Verification") grid.
 *
 * This is the fix for the previously-flagged gap: `Controller\Adminhtml\Approval\Approve` only
 * ever read a single `approval_id` request param, so wiring the grid massaction to it (as the
 * XML originally did) was a silent no-op - Magento's massaction JS posts a `selected`/
 * `selected_all` payload, not `approval_id`. This controller uses the standard
 * `Magento\Ui\Component\MassAction\Filter` helper to resolve that payload against the same
 * collection the grid itself uses, then approves each resolved row one at a time, reusing the
 * exact same `OrderApprovalServiceInterface::approve()` business logic as the single-row
 * `Approve` controller (including its own state/business-rule guards) so bulk and single-row
 * approval can never drift apart.
 */
class MassApprove extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Rgd_CustomerCompliance::approve';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param OrderApprovalServiceInterface $orderApprovalService
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly OrderApprovalServiceInterface $orderApprovalService
    ) {
        parent::__construct($context);
    }

    /**
     * Approve all selected pending orders held for compliance review.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('Something went wrong while resolving the selected rows: %1', $e->getMessage())
            );

            return $resultRedirect->setPath('*/*/index');
        }

        $adminUserId = (int)$this->_session->getUser()->getId();

        $approvedCount = 0;
        $skippedCount = 0;

        foreach ($collection->getItems() as $approval) {
            $approvalId = (int)$approval->getApprovalId();

            try {
                // No notes for a bulk approve - matches the single-row inline "Approve" action's
                // confirm-free, notes-optional behavior (only Reject mandates notes, and Reject
                // is intentionally not offered as a massaction at all - see order_approval_listing.xml).
                $this->orderApprovalService->approve($approvalId, $adminUserId, null);
                $approvedCount++;
            } catch (StateException $e) {
                // Already processed (approved/rejected) - skip rather than fail the whole batch.
                $skippedCount++;
            } catch (Throwable $e) {
                $skippedCount++;
            }
        }

        if ($approvedCount > 0) {
            $this->messageManager->addSuccessMessage(
                __('A total of %1 order approval(s) have been approved.', $approvedCount)
            );
        }

        if ($skippedCount > 0) {
            $this->messageManager->addErrorMessage(
                __(
                    '%1 order approval(s) could not be approved (already processed, or an error occurred).',
                    $skippedCount
                )
            );
        }

        return $resultRedirect->setPath('*/*/index');
    }
}
