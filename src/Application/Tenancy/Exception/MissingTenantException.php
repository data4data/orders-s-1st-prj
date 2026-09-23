<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Exception;

/**
 * Thrown when tenant data is written or required while no store is active.
 */
final class MissingTenantException extends \RuntimeException
{
    public static function forEntity(string $entityClass): self
    {
        return new self(sprintf('Cannot save %s: no store is active. Use TenantContextInterface::runAsStore() for background work.', $entityClass));
    }

    public static function required(): self
    {
        return new self('This operation needs an active store, but none is set.');
    }
}
