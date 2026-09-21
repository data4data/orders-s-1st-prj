<?php

declare(strict_types=1);

namespace App\Domain\Tax;

use App\Domain\Money\Money;
use App\Domain\Shared\Percentage;

/**
 * A VAT rate such as 21.00 % (DECIMAL(5,2) in the database).
 */
final readonly class TaxRate
{
    private function __construct(public Percentage $percentage)
    {
    }

    public static function of(string|int $percentage): self
    {
        return new self(Percentage::of($percentage));
    }

    /** VAT on a net amount, rounded half-up to whole cents. */
    public function taxOn(Money $net): Money
    {
        return $this->percentage->applyTo($net);
    }

    /** Net + VAT. */
    public function grossFor(Money $net): Money
    {
        return $net->plus($this->taxOn($net));
    }

    public function equals(self $other): bool
    {
        return $this->percentage->equals($other->percentage);
    }

    /** "21.00" */
    public function toString(): string
    {
        return $this->percentage->toString();
    }
}
