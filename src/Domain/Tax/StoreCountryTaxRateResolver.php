<?php

declare(strict_types=1);

namespace App\Domain\Tax;

/**
 * Uses the store's country for every sale, whatever the customer's country (decision #26).
 */
final readonly class StoreCountryTaxRateResolver implements TaxRateResolverInterface
{
    public function __construct(private TaxRateTableProviderInterface $tableProvider)
    {
    }

    public function resolve(TaxContext $context): TaxRate
    {
        return $this->tableProvider->table()->rateFor($context->storeCountryCode, $context->taxCategory, $context->date);
    }
}
