<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Http;

use App\Application\Tenancy\AdminStoreSelection;
use App\Application\Tenancy\Port\AdminStoreSelectionStorageInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Keeps the admin store switcher choice in the staff member's session.
 */
final readonly class SessionAdminStoreSelectionStorage implements AdminStoreSelectionStorageInterface
{
    private const KEY = 'admin.store_selection';
    private const ALL_STORES = 'all';

    public function __construct(private RequestStack $requestStack)
    {
    }

    public function current(): ?AdminStoreSelection
    {
        $value = $this->requestStack->getSession()->get(self::KEY);

        return match (true) {
            self::ALL_STORES === $value => AdminStoreSelection::allStores(),
            \is_int($value) => AdminStoreSelection::store($value),
            default => null,
        };
    }

    public function save(AdminStoreSelection $selection): void
    {
        $this->requestStack->getSession()->set(self::KEY, $selection->isAllStores() ? self::ALL_STORES : $selection->storeId);
    }

    public function clear(): void
    {
        $this->requestStack->getSession()->remove(self::KEY);
    }
}
