<?php

declare(strict_types=1);

namespace App\UI\Http\Security;

use App\UI\Http\Error\CsrfTokenExpiredHttpException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Every state-changing JSON API call must send the "api" CSRF token in the X-CSRF-Token header
 * (decision #22). Pages print it in <meta name="csrf-token">; GET /api/csrf-token returns a new
 * one, which the frontend uses to retry once after a 419.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 6)]
final readonly class ApiCsrfListener
{
    public const TOKEN_ID = 'api';
    public const HEADER = 'X-CSRF-Token';

    /** JSON login has no session yet; webhooks (Phase 5) are signed instead. */
    private const EXEMPT_PATHS = ['/api/admin/login'];

    public function __construct(private CsrfTokenManagerInterface $csrfTokenManager)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest()
            || $request->isMethodSafe()
            || !str_starts_with($request->getPathInfo(), '/api/')
            || \in_array($request->getPathInfo(), self::EXEMPT_PATHS, true)) {
            return;
        }

        $token = $request->headers->get(self::HEADER, '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::TOKEN_ID, $token))) {
            throw new CsrfTokenExpiredHttpException();
        }
    }
}
