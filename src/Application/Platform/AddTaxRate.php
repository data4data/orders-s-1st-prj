<?php

declare(strict_types=1);

namespace App\Application\Platform;

/**
 * Adds a VAT rate period; periods of one country and category may not overlap.
 */
final readonly class AddTaxRate
{
    public function __construct(public TaxRateInput $input)
    {
    }
}
