<?php

declare(strict_types=1);

namespace App\Domain\Ordering;

/**
 * What the transition rules need to know about an order, gathered by the workflow guard.
 * Amounts in cents.
 */
final readonly class OrderFacts
{
    public function __construct(
        public OrderState $state,
        public ActorType $actor,
        public int $lineCount,
        public bool $hasBillingAddress,
        public bool $hasShippingAddress,
        public bool $hasShippingMethod,
        public int $totalGross,
        public int $capturedAmount,
        public int $refundedAmount,
    ) {
    }

    public function isFullyPaid(): bool
    {
        return $this->totalGross > 0 && $this->capturedAmount >= $this->totalGross;
    }

    public function isFullyRefunded(): bool
    {
        return $this->capturedAmount > 0 && $this->refundedAmount >= $this->capturedAmount;
    }
}
