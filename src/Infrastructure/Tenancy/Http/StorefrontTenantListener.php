<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Http;

use App\Infrastructure\Persistence\Repository\StoreDomainRepository;
use App\Infrastructure\Tenancy\AdminHost;
use App\Infrastructure\Tenancy\TenantContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Resolves the store from the request host (store_domain) for every storefront request.
 *
 * Priority 40 runs after the session (128) and before the firewall (8), so even loading the
 * logged-in customer is already scoped to the store. An unknown host is a 404 (fail closed).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 40)]
final readonly class StorefrontTenantListener
{
    /** Developer tools that work without a store (profiler, toolbar, Vite dev proxy). Error previews (/_error) stay branded. */
    private const SKIPPED_PATH_PREFIXES = ['/_profiler', '/_wdt', '/build/'];

    public function __construct(
        private StoreDomainRepository $storeDomains,
        private TenantContext $tenantContext,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $host = $request->getHost();
        if (AdminHost::matches($host)) {
            return; // resolved after authentication by AdminTenantListener
        }

        foreach (self::SKIPPED_PATH_PREFIXES as $prefix) {
            if (str_starts_with($request->getPathInfo(), $prefix)) {
                return;
            }
        }

        $domain = $this->storeDomains->findOneByHost($host);
        if (null === $domain || !$domain->getStore()->isActive()) {
            if (str_starts_with($request->getPathInfo(), '/_error/')) {
                return; // dev error-page preview on an unknown host: show the neutral "Store not found" page
            }
            throw StoreNotFoundHttpException::forHost($host);
        }

        $this->tenantContext->useStore($domain->getStore());
    }
}
