<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow;

use App\Entity\Payment;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\TransitionBlocker;

/**
 * A payment is "refunded" only when the refunds add up to its amount (partial refunds stay captured).
 */
#[AsEventListener(event: 'workflow.payment.guard.refund')]
final class PaymentGuardListener
{
    public function __invoke(GuardEvent $event): void
    {
        $payment = $event->getSubject();
        if ($payment instanceof Payment && $payment->refundedAmount() < $payment->getAmount()) {
            $event->addTransitionBlocker(new TransitionBlocker('The payment is not refunded in full yet.', 'partial_refund'));
        }
    }
}
