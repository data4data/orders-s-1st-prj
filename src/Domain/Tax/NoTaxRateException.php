<?php

declare(strict_types=1);

namespace App\Domain\Tax;

final class NoTaxRateException extends \DomainException
{
    public static function for(string $countryCode, string $taxCategory, \DateTimeImmutable $date): self
    {
        return new self(sprintf('No VAT rate is configured for %s / %s on %s.', strtoupper($countryCode), $taxCategory, $date->format('Y-m-d')));
    }
}
