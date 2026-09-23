<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Settings;

/**
 * Adds a host name to the selected store.
 */
final readonly class AddStoreDomain
{
    public function __construct(public string $host)
    {
    }
}
