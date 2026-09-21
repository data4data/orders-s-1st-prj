<?php

declare(strict_types=1);

namespace App\Domain\Discount;

use App\Domain\Money\Money;

/**
 * The cart as the discount rules see it: line nets (before discount), the coupon, and "now".
 */
final readonly class DiscountContext
{
    /**
     * @param list<DiscountableLine> $lines
     */
    public function __construct(
        public string $currency,
        public array $lines,
        public ?Coupon $coupon,
        public \DateTimeImmutable $now,
    ) {
    }

    public function itemsNet(): Money
    {
        $total = Money::zero($this->currency);
        foreach ($this->lines as $line) {
            $total = $total->plus($line->net);
        }

        return $total;
    }

    /**
     * The same cart with a previous rule's discount already taken off each line.
     */
    public function after(DiscountResult $result): self
    {
        $lines = array_map(
            static fn (DiscountableLine $line): DiscountableLine => new DiscountableLine($line->lineId, $line->net->minus($result->forLine($line->lineId))),
            $this->lines,
        );

        return new self($this->currency, $lines, $this->coupon, $this->now);
    }
}
