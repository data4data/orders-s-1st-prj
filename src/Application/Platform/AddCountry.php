<?php

declare(strict_types=1);

namespace App\Application\Platform;

/**
 * Adds a country for addresses and VAT.
 */
final readonly class AddCountry
{
    public function __construct(public string $code, public string $name, public bool $isEu)
    {
    }
}
