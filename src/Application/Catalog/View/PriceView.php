<?php

declare(strict_types=1);

namespace App\Application\Catalog\View;

/**
 * A pack price in cents: gross (shown large), net (small) and per litre. The frontend formats
 * the amounts with the store currency.
 */
final readonly class PriceView
{
    public function __construct(
        public string $currency,
        public int $net,
        public int $gross,
        public ?int $perLitre,
        public string $vatRate,
    ) {
    }
}
