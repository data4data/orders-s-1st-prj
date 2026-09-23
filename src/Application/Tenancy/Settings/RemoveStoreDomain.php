<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Settings;

/**
 * Removes a host name (not the primary one).
 */
final readonly class RemoveStoreDomain
{
    public function __construct(public int $id)
    {
    }
}
