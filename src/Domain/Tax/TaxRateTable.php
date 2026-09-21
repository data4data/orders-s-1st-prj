<?php

declare(strict_types=1);

namespace App\Domain\Tax;

/**
 * All VAT periods, with the guarantee that periods for the same country and category never
 * overlap, so exactly one rate applies on any day.
 */
final readonly class TaxRateTable
{
    /** @var list<TaxRatePeriod> */
    private array $periods;

    /**
     * @param list<TaxRatePeriod> $periods
     */
    public function __construct(array $periods)
    {
        foreach ($periods as $i => $period) {
            foreach (\array_slice($periods, $i + 1) as $other) {
                if ($period->overlaps($other)) {
                    throw new \InvalidArgumentException(sprintf('Overlapping VAT periods for %s/%s starting %s and %s.', $period->countryCode, $period->taxCategory, $period->validFrom, $other->validFrom));
                }
            }
        }
        $this->periods = $periods;
    }

    public function rateFor(string $countryCode, string $taxCategory, \DateTimeImmutable $date): TaxRate
    {
        foreach ($this->periods as $period) {
            if ($period->appliesTo($countryCode, $taxCategory, $date)) {
                return $period->rate;
            }
        }

        throw NoTaxRateException::for($countryCode, $taxCategory, $date);
    }
}
