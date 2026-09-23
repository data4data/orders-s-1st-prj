<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Application\Catalog\CatalogPricing;
use App\Application\Customer\Port\CountryRepositoryInterface;
use App\Application\Ordering\Port\ShippingMethodRepositoryInterface;
use App\Application\Tenancy\TenantContextInterface;
use App\Domain\Money\Money;

/**
 * Human-readable shipping prices (incl. VAT) from the shop's shipping methods, for the landing
 * page, the header top bar and the shipping info page.
 */
final readonly class ShippingInfo
{
    public function __construct(
        private TenantContextInterface $tenantContext,
        private ShippingMethodRepositoryInterface $methods,
        private CountryRepositoryInterface $countries,
        private CatalogPricing $pricing,
    ) {
    }

    /**
     * @return list<array{name: string, description: ?string, gross: ?int, freeFrom: ?int, brackets: list<array{upToKg: ?float, gross: int}>, countries: list<string>}>
     */
    public function methods(): array
    {
        $store = $this->tenantContext->requireStore();
        $names = [];
        foreach ($this->countries->findAll() as $country) {
            $names[$country->getCode()] = $country->getName();
        }

        $result = [];
        foreach ($this->methods->findActive() as $method) {
            $rate = $this->pricing->rateFor($store, $method->getTaxCategory());
            $gross = fn (mixed $cents) => \is_int($cents) ? $rate->grossFor(Money::of($cents, $store->getCurrencyCode()))->amount : null;
            $spec = $method->toSpec();
            $brackets = [];
            foreach (\is_array($spec->config['brackets'] ?? null) ? $spec->config['brackets'] : [] as $bracket) {
                if (\is_array($bracket)) {
                    $brackets[] = ['upToKg' => \is_int($bracket['up_to_grams'] ?? null) ? $bracket['up_to_grams'] / 1000 : null, 'gross' => (int) $gross($bracket['amount'] ?? 0)];
                }
            }
            $result[] = [
                'name' => $method->getName(),
                'description' => $method->getDescription(),
                'gross' => $gross($spec->config['amount'] ?? null),
                // The free-shipping threshold is compared with the gross order value (decision #60).
                'freeFrom' => \is_int($spec->config['threshold'] ?? null) ? $spec->config['threshold'] : null,
                'brackets' => $brackets,
                'countries' => array_map(static fn (string $code) => $names[$code] ?? $code, $spec->allowedCountries ?? array_keys($names)),
            ];
        }

        return $result;
    }

    /**
     * @return list<array{name: string, gross: ?int, freeFrom: ?int}>
     */
    public function summary(): array
    {
        return array_map(static fn (array $m) => ['name' => $m['name'], 'gross' => $m['gross'], 'freeFrom' => $m['freeFrom']], $this->methods());
    }
}
