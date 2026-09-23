<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Domain\Money\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Price per litre, shown next to every pack price (EU unit-price rule, decision #45).
 */
final class UnitPrice
{
    /** pack price × 1000 / volume in ml, rounded half-up once to whole cents. */
    public static function perLitre(Money $packPrice, int $volumeMl): Money
    {
        if ($volumeMl <= 0) {
            throw new \InvalidArgumentException('The pack volume must be more than 0 ml.');
        }

        $cents = BigDecimal::of($packPrice->amount)->multipliedBy(1000)->dividedBy($volumeMl, 0, RoundingMode::HalfUp);

        return Money::of($cents->toInt(), $packPrice->currency);
    }
}
