<?php

declare(strict_types=1);

namespace App\Application\Tenancy\View;

/**
 * What the current request is scoped to: one store, all stores (read-only) or nothing yet.
 */
final readonly class TenantStatusView
{
    public const MODE_STORE = 'store';
    public const MODE_ALL_STORES = 'all';
    public const MODE_NONE = 'none';

    public function __construct(
        public string $mode,
        public ?StoreView $store,
        public bool $readOnly,
    ) {
    }
}
