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
 * Refund strategy for orders paid via Razorpay, routed through Magento's own online-refund
 * abstraction rather than calling the Razorpay SDK directly.
 *
 * EMPIRICALLY UNVERIFIED PATH (per the Eng spec's flagged open item): this strategy assumes
 * `\Magento\Sales\Model\Service\CreditmemoService::refund($creditmemo, true)` correctly routes
 * through the razorpay/magento module's payment method `refund()` implementation the same way
 * it does for other online-capable gateways. That assumption has not been verified against
 * the actual installed `razorpay/magento` module and must be confirmed during integration
 * testing before this strategy is relied on in production.
 */
class RazorpayOnlineRefundStrategy implements RefundStrategyInterface
{
    private const PAYMENT_METHOD_CODE = 'razorpay';

    private const PAYMENT_METHOD_CLASS = \Razorpay\Magento\Model\PaymentMethod::class;

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
        try {
            if (!class_exists(self::PAYMENT_METHOD_CLASS)) {
                // The razorpay/magento module is not installed - never claim capability.
                return false;
            }

            $order = $this->orderRepository->get($orderId);
            $payment = $order->getPayment();

            return $payment !== null && $payment->getMethod() === self::PAYMENT_METHOD_CODE;
        } catch (\Throwable $e) {
            // A capability probe must never throw.
            return false;
        }
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

            // $isOnline = true routes the refund through the order's assigned payment
            // method's own refund() implementation - this is the Magento-idiomatic way to
            // trigger an online refund for any gateway, including Razorpay.
            $this->creditmemoService->refund($creditmemo, true);

            $result->setStatus(RefundResultInterface::STATUS_COMPLETED)
                ->setReference($creditmemo->getIncrementId())
                ->setMessage((string)__('Refund processed online via Razorpay.'));
        } catch (\Exception $e) {
            $this->logger->error(
                'Razorpay online refund failed for order "' . $orderId . '": ' . $e->getMessage(),
                ['exception' => $e]
            );

            $result->setStatus(RefundResultInterface::STATUS_FAILED)
                ->setReference(null)
                ->setMessage($e->getMessage());
        }

        return $result;
    }
}
