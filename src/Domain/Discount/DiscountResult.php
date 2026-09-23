<?php

declare(strict_types=1);

namespace App\Domain\Discount;

use App\Domain\Money\Money;

/**
 * A discount, split over the lines (the split always adds up to the total).
 */
final readonly class DiscountResult
{
    /**
     * @param array<string, Money> $perLine line id => net discount on that line
     * @param list<string>         $codes   applied coupon codes
     */
    public function __construct(
        public Money $total,
        public array $perLine,
        public array $codes = [],
    ) {
    }

    public static function none(string $currency): self
    {
        return new self(Money::zero($currency), []);
    }

    public function forLine(string $lineId): Money
    {
        return $this->perLine[$lineId] ?? Money::zero($this->total->currency);
    }

    public function plus(self $other): self
    {
        $perLine = $this->perLine;
        foreach ($other->perLine as $lineId => $amount) {
            $perLine[$lineId] = isset($perLine[$lineId]) ? $perLine[$lineId]->plus($amount) : $amount;
        }

        return new self($this->total->plus($other->total), $perLine, [...$this->codes, ...$other->codes]);
    }
}
