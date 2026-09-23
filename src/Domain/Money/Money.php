<?php

declare(strict_types=1);

namespace App\Domain\Money;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;

/**
 * An amount in minor units (cents) of one currency. Every supported currency (EUR, PLN, …)
 * has two decimals. Immutable; all arithmetic is exact integer arithmetic.
 */
final readonly class Money
{
    private function __construct(
        public int $amount,
        public string $currency,
    ) {
    }

    public static function of(int $amount, string $currency): self
    {
        if (1 !== preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a three-letter ISO 4217 currency code.', $currency));
        }

        return new self($amount, $currency);
    }

    public static function zero(string $currency): self
    {
        return self::of(0, $currency);
    }

    /**
     * Creates money from a decimal string such as "49.95" (at most two decimals).
     */
    public static function fromDecimal(string $decimal, string $currency): self
    {
        $value = BigDecimal::of($decimal);
        if ($value->getScale() > 2) {
            throw new \InvalidArgumentException(sprintf('"%s" has more than two decimals.', $decimal));
        }

        return self::of($value->toScale(2)->getUnscaledValue()->toInt(), $currency);
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multipliedBy(int $factor): self
    {
        return new self($this->amount * $factor, $this->currency);
    }

    /**
     * Multiplies by an exact decimal factor and rounds half-up to whole cents.
     */
    public function multipliedByDecimal(BigDecimal $factor): self
    {
        return new self(
            BigDecimal::of($this->amount)->multipliedBy($factor)->toScale(0, RoundingMode::HalfUp)->toInt(),
            $this->currency,
        );
    }

    public function min(self $other): self
    {
        $this->assertSameCurrency($other);

        return $this->amount <= $other->amount ? $this : $other;
    }

    public function isZero(): bool
    {
        return 0 === $this->amount;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount > $other->amount;
    }

    public function isGreaterThanOrEqualTo(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount >= $other->amount;
    }

    public function isLessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount < $other->amount;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->amount === $other->amount;
    }

    /**
     * Splits this amount over the given weights without losing or creating a cent
     * (largest-remainder method; ties go to the earlier key).
     *
     * @template K of array-key
     *
     * @param array<K, int> $weights non-negative weights, e.g. line nets in cents
     *
     * @return array<K, self> the parts, in the same key order; they always add up to this amount
     */
    public function allocate(array $weights): array
    {
        if ($this->amount < 0) {
            throw new \InvalidArgumentException('Only non-negative amounts can be allocated.');
        }
        if ([] === $weights) {
            throw new \InvalidArgumentException('At least one weight is needed to allocate an amount.');
        }

        $total = 0;
        foreach ($weights as $weight) {
            if ($weight < 0) {
                throw new \InvalidArgumentException('Allocation weights cannot be negative.');
            }
            $total += $weight;
        }

        if (0 === $total) {
            if (0 !== $this->amount) {
                throw new \InvalidArgumentException('Cannot allocate a non-zero amount when all weights are zero.');
            }

            return array_map(fn (int $weight): self => new self(0, $this->currency), $weights);
        }

        $shares = [];
        $remainders = [];
        $allocated = 0;
        foreach ($weights as $key => $weight) {
            $product = BigInteger::of($this->amount)->multipliedBy($weight);
            $share = $product->quotient($total)->toInt();
            $shares[$key] = $share;
            $remainders[$key] = $product->remainder($total)->toInt();
            $allocated += $share;
        }

        // Hand out the cents lost to rounding down: largest remainder first, earlier key on ties.
        $position = array_flip(array_keys($weights));
        $keys = array_keys($remainders);
        usort($keys, static fn ($a, $b): int => [$remainders[$b], $position[$a]] <=> [$remainders[$a], $position[$b]]);
        for ($i = 0, $left = $this->amount - $allocated; $i < $left; ++$i) {
            ++$shares[$keys[$i]];
        }

        return array_map(fn (int $share): self => new self($share, $this->currency), $shares);
    }

    /** "49.95" */
    public function toDecimalString(): string
    {
        return (string) BigDecimal::ofUnscaledValue($this->amount, 2);
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw CurrencyMismatchException::between($this->currency, $other->currency);
        }
    }
}
