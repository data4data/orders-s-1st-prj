<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Application\Catalog\View\PriceView;
use App\Domain\Pricing\UnitPrice;
use App\Domain\Tax\TaxContext;
use App\Domain\Tax\TaxRate;
use App\Domain\Tax\TaxRateResolverInterface;
use App\Entity\ProductVariant;
use App\Entity\Store;
use App\Entity\TaxCategory;
use Psr\Clock\ClockInterface;

/**
 * Catalog prices: entered net, shown gross (store country's VAT today, decision #26), plus the
 * price per litre (decision #45). All maths is done by the pure Domain classes.
 */
final class CatalogPricing
{
    /** @var array<string, TaxRate> */
    private array $rates = [];

    public function __construct(
        private readonly TaxRateResolverInterface $taxRates,
        private readonly ClockInterface $clock,
    ) {
    }

    public function rateFor(Store $store, TaxCategory $taxCategory): TaxRate
    {
        $key = $store->getCountry()->getCode().'/'.$taxCategory->getCode().'/'.$this->clock->now()->format('Y-m-d');

        return $this->rates[$key] ??= $this->taxRates->resolve(new TaxContext($store->getCountry()->getCode(), $taxCategory->getCode(), $this->clock->now()));
    }

    public function price(Store $store, ProductVariant $variant): PriceView
    {
        $rate = $this->rateFor($store, $variant->getProduct()->getTaxCategory());
        $net = $variant->priceNet($store->getCurrencyCode());
        $gross = $rate->grossFor($net);

        return new PriceView(
            $store->getCurrencyCode(),
            $net->amount,
            $gross->amount,
            $variant->getVolumeMl() > 0 ? UnitPrice::perLitre($gross, $variant->getVolumeMl())->amount : null,
            $rate->toString(),
        );
    }

    /**
     * "1.2100" per tax category id, for filtering and sorting catalog prices by gross in SQL.
     *
     * @param list<TaxCategory> $taxCategories
     *
     * @return array<int, string>
     */
    public function grossFactors(Store $store, array $taxCategories): array
    {
        $factors = [];
        foreach ($taxCategories as $taxCategory) {
            $factors[(int) $taxCategory->getId()] = (string) $this->rateFor($store, $taxCategory)->percentage->asFraction()->plus(1);
        }

        return $factors;
    }
}
