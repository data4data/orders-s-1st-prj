<?php

declare(strict_types=1);

namespace App\Domain\Money;

final class CurrencyMismatchException extends \DomainException
{
    public static function between(string $a, string $b): self
    {
        return new self(sprintf('Cannot combine amounts in %s and %s.', $a, $b));
    }
}
