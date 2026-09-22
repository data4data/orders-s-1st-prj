<?php

declare(strict_types=1);

namespace App\Application\Ordering;

use App\Application\Ordering\Port\PaymentRepositoryInterface;
use App\Domain\Ordering\OrderFacts;
use App\Entity\Order;

/**
 * Collects the facts the pure OrderTransitionPolicy decides on.
 */
final readonly class OrderFactsProvider
{
    public function __construct(
        private PaymentRepositoryInterface $payments,
        private OrderActorResolverInterface $actors,
    ) {
    }

    public function facts(Order $order): OrderFacts
    {
        $captured = 0;
        $refunded = 0;
        foreach ($this->payments->forOrder($order) as $payment) {
            $captured += $payment->capturedAmount();
            $refunded += $payment->refundedAmount();
        }

        return new OrderFacts(
            $order->state(),
            $this->actors->current()->type,
            $order->getItems()->count(),
            !$order->getBillingAddress()->isEmpty(),
            !$order->getShippingAddress()->isEmpty(),
            null !== $order->getShippingMethodName(),
            $order->getTotalGross(),
            $captured,
            $refunded,
        );
    }
}
