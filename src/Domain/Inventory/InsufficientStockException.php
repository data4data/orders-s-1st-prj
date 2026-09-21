<?php

declare(strict_types=1);

namespace App\Domain\Inventory;

final class InsufficientStockException extends \DomainException
{
    public static function toReserve(int $requested, int $available): self
    {
        return new self(sprintf('Cannot reserve %d: only %d available.', $requested, $available));
    }

    public static function notReserved(int $requested, int $reserved): self
    {
        return new self(sprintf('Cannot use %d reserved units: only %d are reserved.', $requested, $reserved));
    }
}
