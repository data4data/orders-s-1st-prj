<?php

declare(strict_types=1);

namespace App\Domain\Tax;

/**
 * Chooses the VAT rate for a sale (decision #26: the store's country decides for now).
 */
interface TaxRateResolverInterface
{
    /** @throws NoTaxRateException */
    public function resolve(TaxContext $context): TaxRate;
}
