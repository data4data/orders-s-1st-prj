<?php

declare(strict_types=1);

namespace App\Application\Platform;

/**
 * Adds a VAT category (e.g. reduced).
 */
final readonly class CreateTaxCategory
{
    public function __construct(public string $code, public string $name)
    {
    }
}
