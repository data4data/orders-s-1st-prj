<?php

declare(strict_types=1);

namespace App\UI\Http\Security;

use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Consumes one token of a per-action limiter (config/packages/rate_limiter.yaml) or answers 429
 * with Retry-After.
 */
final class RateLimitGuard
{
    public static function consume(RateLimiterFactoryInterface $factory, string $key): void
    {
        $limit = $factory->create($key)->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(max(1, $limit->getRetryAfter()->getTimestamp() - time()), 'Too many attempts. Please wait before trying again.');
        }
    }
}
