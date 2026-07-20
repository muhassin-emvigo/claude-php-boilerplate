<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Plugin\Sales;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Psr\Log\LoggerInterface;
use Rgd\CustomerCompliance\Api\OrderApprovalServiceInterface;
use Throwable;

/**
 * After-plugin on order placement that triggers the compliance verification hold.
 *
 * Design tradeoff: a failure inside {@see OrderApprovalServiceInterface::holdForVerification()}
 * is deliberately caught and logged here rather than allowed to propagate. Letting it bubble up
 * would abort the entire order-placement flow at the framework level (payment already captured,
 * inventory already reserved, etc.) for what is, from the customer's perspective, a back-office
 * compliance concern. Instead we accept the risk that an order could end up NOT placed on hold
 * if this call fails, and rely on the logged error plus operational monitoring/alerting to catch
 * that gap rather than blocking checkout. This is a conscious business decision, not an oversight.
 */
class HoldOrderForVerificationPlugin
{
    /**
     * @param OrderApprovalServiceInterface $orderApprovalService
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly OrderApprovalServiceInterface $orderApprovalService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Trigger the compliance hold after the core order placement completes.
     *
     * The order approval service is responsible for mutating/persisting the order's own state
     * (e.g. status/state changes) as a side effect of placing it on hold; this plugin's sole
     * responsibility is to invoke that service after placement and return the result unchanged.
     *
     * @param OrderManagementInterface $subject
     * @param OrderInterface $result
     * @return OrderInterface
     */
    public function afterPlace(OrderManagementInterface $subject, OrderInterface $result): OrderInterface
    {
        try {
            $this->orderApprovalService->holdForVerification((int)$result->getEntityId());
        } catch (Throwable $e) {
            $this->logger->error(
                sprintf(
                    'Rgd_CustomerCompliance: failed to place order #%s on compliance verification hold: %s',
                    (string)$result->getEntityId(),
                    $e->getMessage()
                ),
                ['exception' => $e]
            );
        }

        return $result;
    }
}
