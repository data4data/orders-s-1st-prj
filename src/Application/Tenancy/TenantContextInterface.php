<?php

declare(strict_types=1);

namespace App\Application\Tenancy;

use App\Entity\Store;

/**
 * The store the current request, console command or message works for.
 *
 * Without a store, tenant queries return nothing (fail closed). Platform mode switches the
 * tenant filter off on purpose, e.g. for fixtures or the super-admin's "All stores" view.
 */
interface TenantContextInterface
{
    /** The active store, or null when no store is set or in platform mode. */
    public function getStore(): ?Store;

    /** @throws Exception\MissingTenantException when no store is active */
    public function requireStore(): Store;

    public function isPlatformMode(): bool;

    /** True in the super-admin's "All stores" view: nothing may be written. */
    public function isReadOnly(): bool;

    /**
     * Runs $callback with the tenant filter disabled, then restores the previous state.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function runAsPlatform(callable $callback): mixed;

    /**
     * Runs $callback scoped to $store, then restores the previous state.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function runAsStore(Store $store, callable $callback): mixed;
}
