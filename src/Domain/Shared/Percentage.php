<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Domain\Money\Money;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;

/**
 * A percentage between 0 and 100 with at most two decimals, e.g. "21.00" or "5.50"
 * (stored as DECIMAL(5,2)). Never a float.
 */
final readonly class Percentage
{
    private function __construct(private BigDecimal $value)
    {
    }

    public static function of(string|int $value): self
    {
        try {
            $decimal = BigDecimal::of($value);
        } catch (MathException) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a number.', $value));
        }

        if ($decimal->getScale() > 2) {
            throw new \InvalidArgumentException(sprintf('Percentage "%s" has more than two decimals.', $value));
        }
        if ($decimal->isNegative() || $decimal->isGreaterThan(100)) {
            throw new \InvalidArgumentException(sprintf('Percentage "%s" must be between 0 and 100.', $value));
        }

        return new self($decimal->toScale(2));
    }

    /**
     * This percentage of $money, rounded half-up to whole cents.
     */
    public function applyTo(Money $money): Money
    {
        return $money->multipliedByDecimal($this->asFraction());
    }

    public function isZero(): bool
    {
        return $this->value->isZero();
    }

    public function equals(self $other): bool
    {
        return $this->value->isEqualTo($other->value);
    }

    /** "21.00" */
    public function toString(): string
    {
        return (string) $this->value;
    }

    /** 21% as 0.21 (exact). */
    public function asFraction(): BigDecimal
    {
        return $this->value->dividedBy(100, 4);
    }
}
