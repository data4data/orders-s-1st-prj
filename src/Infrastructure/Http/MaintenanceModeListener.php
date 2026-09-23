<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * While var/maintenance.flag exists every page answers 503 (see: bin/console app:maintenance on|off).
 *
 * Priority 100: after the session is attached (128), so the branded 503 page can render, and before
 * routing (32), the firewall (8) and store resolution.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 100)]
final readonly class MaintenanceModeListener
{
    public const RETRY_AFTER_SECONDS = 300;

    public function __construct(
        #[Autowire('%app.maintenance_flag%')]
        private string $flagFile,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !is_file($this->flagFile)) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (str_starts_with($path, '/_profiler') || str_starts_with($path, '/_wdt') || str_starts_with($path, '/build/')) {
            return;
        }

        throw new ServiceUnavailableHttpException(self::RETRY_AFTER_SECONDS, 'The shop is being updated. Please try again in a few minutes.');
    }
}
