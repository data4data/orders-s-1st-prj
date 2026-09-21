<?php

declare(strict_types=1);

namespace App\Domain\Shipping;

/**
 * A store's shipping method as the domain sees it (mirrors shipping_method).
 */
final readonly class ShippingMethodSpec
{
    /** @var list<string>|null */
    public ?array $allowedCountries;

    /**
     * @param array<string, mixed> $config
     * @param list<string>|null    $allowedCountries null or empty = ships everywhere
     */
    public function __construct(
        public string $code,
        public string $calculatorCode,
        public array $config,
        ?array $allowedCountries = null,
    ) {
        $this->allowedCountries = null === $allowedCountries || [] === $allowedCountries
            ? null
            : array_map(strtoupper(...), $allowedCountries);
    }

    public function shipsTo(string $countryCode): bool
    {
        return null === $this->allowedCountries || \in_array(strtoupper($countryCode), $this->allowedCountries, true);
    }
}
