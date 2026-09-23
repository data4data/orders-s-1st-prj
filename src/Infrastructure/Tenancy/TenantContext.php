<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy;

use App\Application\Tenancy\Exception\MissingTenantException;
use App\Application\Tenancy\TenantContextInterface;
use App\Entity\Store;
use App\Infrastructure\Tenancy\Doctrine\TenantFilter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Holds the active store and keeps the Doctrine tenant filter in sync with it.
 *
 * The filter is enabled for every entity manager from the start (doctrine.yaml), so a request
 * without a store sees no tenant rows at all. Reset after every request and every message.
 */
final class TenantContext implements TenantContextInterface, ResetInterface
{
    private ?Store $store = null;
    private bool $platformMode = false;
    private bool $readOnly = false;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function getStore(): ?Store
    {
        return $this->platformMode ? null : $this->store;
    }

    public function requireStore(): Store
    {
        return $this->getStore() ?? throw MissingTenantException::required();
    }

    public function isPlatformMode(): bool
    {
        return $this->platformMode;
    }

    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    public function useStore(Store $store): void
    {
        if (null === $store->getId()) {
            throw new \LogicException('Only a saved store can become the active store.');
        }
        $this->apply($store, false, false);
    }

    /**
     * Switches the tenant filter off. $readOnly blocks all writes (super-admin "All stores").
     */
    public function usePlatform(bool $readOnly): void
    {
        $this->apply(null, true, $readOnly);
    }

    public function clear(): void
    {
        $this->apply(null, false, false);
    }

    public function reset(): void
    {
        $this->clear();
    }

    public function runAsPlatform(callable $callback): mixed
    {
        return $this->runWith(null, true, $callback);
    }

    public function runAsStore(Store $store, callable $callback): mixed
    {
        return $this->runWith($store, false, $callback);
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    private function runWith(?Store $store, bool $platformMode, callable $callback): mixed
    {
        [$previousStore, $previousPlatform, $previousReadOnly] = [$this->store, $this->platformMode, $this->readOnly];
        $this->apply($store, $platformMode, false);

        try {
            return $callback();
        } finally {
            $this->apply($previousStore, $previousPlatform, $previousReadOnly);
        }
    }

    private function apply(?Store $store, bool $platformMode, bool $readOnly): void
    {
        $this->store = $store;
        $this->platformMode = $platformMode;
        $this->readOnly = $readOnly;

        $filters = $this->entityManager->getFilters();
        if ($filters->isEnabled(TenantFilter::NAME)) {
            // A filter parameter cannot be removed, so the filter is re-created on every change.
            $filters->disable(TenantFilter::NAME);
        }
        if ($platformMode) {
            return;
        }

        $filter = $filters->enable(TenantFilter::NAME);
        if (null !== $store) {
            $filter->setParameter(TenantFilter::PARAMETER, $store->getId(), 'integer');
        }
    }
}
