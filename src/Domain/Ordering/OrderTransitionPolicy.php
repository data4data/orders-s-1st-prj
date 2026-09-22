<?php

declare(strict_types=1);

namespace App\Domain\Ordering;

/**
 * The guard rules of the order state machine (architecture.md §4). The state machine itself only
 * knows which edges exist; this policy decides whether an existing edge may be taken now.
 */
final class OrderTransitionPolicy
{
    /**
     * @return list<TransitionBlock> empty when the transition is allowed
     */
    public function blocks(string $transition, OrderFacts $order): array
    {
        return match ($transition) {
            'checkout' => $this->checkout($order),
            'pay' => $order->isFullyPaid() ? [] : [TransitionBlock::NotPaid],
            'start_processing' => $this->staff($order),
            // Defence in depth: the state machine has no edge from payment_pending to shipped anyway.
            'ship' => [...$this->staff($order), ...($order->isFullyPaid() ? [] : [TransitionBlock::NotPaid])],
            // A carrier webhook may report the delivery too.
            'deliver' => ActorType::Customer === $order->actor ? [TransitionBlock::StaffOnly] : [],
            'cancel' => $this->cancel($order),
            'refund' => $this->staffThenRefunded($order),
            default => [],
        };
    }

    public function allows(string $transition, OrderFacts $order): bool
    {
        return [] === $this->blocks($transition, $order);
    }

    /**
     * @return list<TransitionBlock>
     */
    private function checkout(OrderFacts $order): array
    {
        $blocks = [];
        if (0 === $order->lineCount) {
            $blocks[] = TransitionBlock::NoLines;
        }
        if (!$order->hasBillingAddress || !$order->hasShippingAddress) {
            $blocks[] = TransitionBlock::AddressMissing;
        }
        if (!$order->hasShippingMethod) {
            $blocks[] = TransitionBlock::ShippingMethodMissing;
        }

        return $blocks;
    }

    /**
     * Before payment anybody involved may cancel (customer, staff, expiry job). Once paid, only
     * staff, and only after the full refund went through (decision #55).
     *
     * @return list<TransitionBlock>
     */
    private function cancel(OrderFacts $order): array
    {
        if (\in_array($order->state, [OrderState::Draft, OrderState::PaymentPending], true)) {
            return [];
        }

        return $this->staffThenRefunded($order);
    }

    /**
     * Non-staff only learn that it is staff-only; staff learn what is still missing.
     *
     * @return list<TransitionBlock>
     */
    private function staffThenRefunded(OrderFacts $order): array
    {
        if (ActorType::Staff !== $order->actor) {
            return [TransitionBlock::StaffOnly];
        }

        return $order->isFullyRefunded() ? [] : [TransitionBlock::NotRefunded];
    }

    /**
     * @return list<TransitionBlock>
     */
    private function staff(OrderFacts $order): array
    {
        return ActorType::Staff === $order->actor ? [] : [TransitionBlock::StaffOnly];
    }
}
