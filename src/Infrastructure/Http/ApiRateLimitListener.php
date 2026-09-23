<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Entity\StaffUser;
use App\Infrastructure\Tenancy\AdminHost;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * General API limits (pages.html → Error handling → Rate limits): storefront API 300/min per IP,
 * admin API 600/min per staff user. Stricter per-action limits (login, coupons, checkout…) are
 * applied where those actions live.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 5)]
final readonly class ApiRateLimitListener
{
    public function __construct(
        #[Target('storefront_api.limiter')] private RateLimiterFactoryInterface $storefrontApi,
        #[Target('admin_api.limiter')] private RateLimiterFactoryInterface $adminApi,
        private Security $security,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $user = $this->security->getUser();
        $limiter = AdminHost::matches($request->getHost()) && $user instanceof StaffUser
            ? $this->adminApi->create('staff-'.$user->getId())
            : $this->storefrontApi->create($request->getClientIp() ?? 'unknown');

        $limit = $limiter->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(max(1, $limit->getRetryAfter()->getTimestamp() - time()), 'Too many requests. Please wait a moment and try again.');
        }
    }
}
