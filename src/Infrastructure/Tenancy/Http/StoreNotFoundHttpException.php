<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Http;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * No active store for the request host. Rendered as the neutral "Store not found" page.
 */
final class StoreNotFoundHttpException extends NotFoundHttpException
{
    public static function forHost(string $host): self
    {
        return new self(sprintf('No active store is configured for host "%s".', $host));
    }
}
