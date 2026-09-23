<?php

declare(strict_types=1);

namespace App\Application\Tenancy;

/**
 * What a staff member picked in the admin store switcher: one store, or "All stores".
 */
final readonly class AdminStoreSelection
{
    private function __construct(public ?int $storeId)
    {
    }

    public static function store(int $storeId): self
    {
        return new self($storeId);
    }

    public static function allStores(): self
    {
        return new self(null);
    }

    public function isAllStores(): bool
    {
        return null === $this->storeId;
    }
}
