<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/**
 * A number of units of one item (at least 1).
 */
final readonly class Quantity
{
    private function __construct(public int $value)
    {
    }

    public static function of(int $value): self
    {
        if ($value < 1) {
            throw new \InvalidArgumentException(sprintf('A quantity must be at least 1, got %d.', $value));
        }

        return new self($value);
    }

    public function plus(self $other): self
    {
        return new self($this->value + $other->value);
    }
}
