<?php

declare(strict_types=1);

namespace App\Domain\Tax;

/**
 * Supplies the VAT table (implemented on top of the tax_rate table in Phase 4).
 */
interface TaxRateTableProviderInterface
{
    public function table(): TaxRateTable;
}
