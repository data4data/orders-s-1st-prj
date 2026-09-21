<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Http;

use App\Infrastructure\Persistence\Repository\StoreDomainRepository;
use App\Infrastructure\Tenancy\AdminHost;
use App\Infrastructure\Tenancy\TenantContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
    /** Framework paths that work without a store (profiler, toolbar, error previews, Vite dev proxy). */
    private const SKIPPED_PATH_PREFIXES = ['/_', '/build/'];

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
            throw new NotFoundHttpException(sprintf('No active store is configured for host "%s".', $host));
        }

        $this->tenantContext->useStore($domain->getStore());
    }
}
