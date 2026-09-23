<?php

declare(strict_types=1);

namespace App\Domain\Tax;

/**
 * One row of the platform-wide VAT table: the rate for a country and tax category in a
 * period. Both dates are inclusive; no end date means the rate is still valid.
 */
final readonly class TaxRatePeriod
{
    public string $countryCode;
    public string $validFrom;
    public ?string $validTo;

    public function __construct(
        string $countryCode,
        public string $taxCategory,
        public TaxRate $rate,
        \DateTimeImmutable $validFrom,
        ?\DateTimeImmutable $validTo = null,
    ) {
        $this->countryCode = strtoupper($countryCode);
        $this->validFrom = $validFrom->format('Y-m-d');
        $this->validTo = $validTo?->format('Y-m-d');

        if (null !== $this->validTo && $this->validTo < $this->validFrom) {
            throw new \InvalidArgumentException(sprintf('VAT period for %s/%s ends (%s) before it starts (%s).', $this->countryCode, $taxCategory, $this->validTo, $this->validFrom));
        }
    }

    public function appliesTo(string $countryCode, string $taxCategory, \DateTimeImmutable $date): bool
    {
        $day = $date->format('Y-m-d');

        return $this->countryCode === strtoupper($countryCode)
            && $this->taxCategory === $taxCategory
            && $day >= $this->validFrom
            && (null === $this->validTo || $day <= $this->validTo);
    }

    public function overlaps(self $other): bool
    {
        return $this->countryCode === $other->countryCode
            && $this->taxCategory === $other->taxCategory
            && (null === $other->validTo || $this->validFrom <= $other->validTo)
            && (null === $this->validTo || $other->validFrom <= $this->validTo);
    }
}
