<?php

declare(strict_types=1);

namespace App\Domain\Shipping;

final class InvalidShippingConfigException extends \DomainException
{
    public static function missing(string $calculator, string $key): self
    {
        return new self(sprintf('Shipping calculator "%s" needs a non-negative integer "%s" in its config.', $calculator, $key));
    }

    public static function unknownCalculator(string $code): self
    {
        return new self(sprintf('No shipping calculator with code "%s" exists.', $code));
    }
}
