<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Plugin\Sales;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\ShipmentTrackInterface;
use Magento\Sales\Api\Data\ShipmentCommentCreationInterface;
use Magento\Sales\Api\ShipOrderInterface;
use Rgd\CustomerCompliance\Api\Data\OrderApprovalInterface;
use Rgd\CustomerCompliance\Api\OrderApprovalRepositoryInterface;

/**
 * Before-plugin on shipment creation that blocks shipping orders which are still pending
 * (or have failed) compliance verification.
 */
class BlockShipmentPlugin
{
    /**
     * @param OrderApprovalRepositoryInterface $orderApprovalRepository
     */
    public function __construct(
        private readonly OrderApprovalRepositoryInterface $orderApprovalRepository
    ) {
    }

    /**
     * Guard shipment creation on the linked order's compliance approval status.
     *
     * @param ShipOrderInterface $subject
     * @param int $orderId
     * @param array $items
     * @param bool $notify
     * @param bool $appendComment
     * @param ShipmentTrackInterface[]|null $tracks
     * @param array $packages
     * @param ShipmentCommentCreationInterface|null $comment
     * @return array
     * @throws LocalizedException
     */
    public function beforeExecute(
        ShipOrderInterface $subject,
        int $orderId,
        array $items = [],
        bool $notify = false,
        bool $appendComment = false,
        ?array $tracks = null,
        array $packages = [],
        ?ShipmentCommentCreationInterface $comment = null
    ): array {
        try {
            $approval = $this->orderApprovalRepository->getByOrderId($orderId);
        } catch (NoSuchEntityException $e) {
            // No approval record exists for this order: there is nothing to gate on, allow it.
            return [$orderId, $items, $notify, $appendComment, $tracks, $packages, $comment];
        }

        if ($approval->getStatus() !== OrderApprovalInterface::STATUS_APPROVED) {
            throw new LocalizedException(
                __('This order cannot be shipped until compliance verification is approved.')
            );
        }

        return [$orderId, $items, $notify, $appendComment, $tracks, $packages, $comment];
    }
}
