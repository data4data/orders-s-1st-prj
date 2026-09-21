<?php

declare(strict_types=1);

namespace App\Domain\Shipping;

final class ShippingNotAvailableException extends \DomainException
{
    public static function toCountry(string $methodCode, string $countryCode): self
    {
        return new self(sprintf('Shipping method "%s" does not ship to %s.', $methodCode, $countryCode));
    }

    public static function forWeight(string $methodCode, int $weightGrams): self
    {
        return new self(sprintf('Shipping method "%s" cannot ship %d g.', $methodCode, $weightGrams));
    }
}
