<?php

declare(strict_types=1);

namespace App\Domain\Tax;

/**
 * Everything a VAT rule may need to pick a rate. The destination country is carried along
 * so a destination-based rule (EU OSS) can be added later without changing callers.
 */
final readonly class TaxContext
{
    public function __construct(
        public string $storeCountryCode,
        public string $taxCategory,
        public \DateTimeImmutable $date,
        public ?string $destinationCountryCode = null,
    ) {
    }
}
