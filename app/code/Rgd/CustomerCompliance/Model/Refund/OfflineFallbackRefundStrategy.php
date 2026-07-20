<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model\Refund;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Service\CreditmemoService;
use Psr\Log\LoggerInterface;
use Rgd\CustomerCompliance\Api\Data;
use Rgd\CustomerCompliance\Api\Data\RefundResultInterface;
use Rgd\CustomerCompliance\Api\RefundStrategyInterface;
use Rgd\CustomerCompliance\Model\Data\RefundResultFactory;

/**
 * Guaranteed-fallback refund strategy: records an offline credit memo in Magento without
 * calling any payment gateway. Always reports itself as able to refund, since it is the
 * strategy of last resort.
 */
class OfflineFallbackRefundStrategy implements RefundStrategyInterface
{
    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param CreditmemoFactory $creditmemoFactory
     * @param CreditmemoService $creditmemoService
     * @param RefundResultFactory $refundResultFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CreditmemoFactory $creditmemoFactory,
        private readonly CreditmemoService $creditmemoService,
        private readonly RefundResultFactory $refundResultFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function canRefund(int $orderId): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function refund(int $orderId, float $amount, string $reason): Data\RefundResultInterface
    {
        /** @var RefundResultInterface $result */
        $result = $this->refundResultFactory->create();

        try {
            $order = $this->orderRepository->get($orderId);
            $creditmemo = $this->creditmemoFactory->createByOrder($order, ['comment_text' => $reason]);

            // $isOnline = false records the credit memo in Magento without calling any
            // gateway - the actual money movement must still be handled manually.
            $this->creditmemoService->refund($creditmemo, false);

            $result->setStatus(RefundResultInterface::STATUS_MANUAL_FALLBACK)
                ->setReference($creditmemo->getIncrementId())
                ->setMessage(
                    (string)__(
                        'An offline credit memo was created; the gateway-side refund still needs to be'
                        . ' completed manually.'
                    )
                );
        } catch (\Exception $e) {
            $this->logger->error(
                'Offline fallback refund failed for order "' . $orderId . '": ' . $e->getMessage(),
                ['exception' => $e]
            );

            $result->setStatus(RefundResultInterface::STATUS_FAILED)
                ->setReference(null)
                ->setMessage($e->getMessage());
        }

        return $result;
    }
}
