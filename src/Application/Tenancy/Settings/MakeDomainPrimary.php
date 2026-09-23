<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Settings;

/**
 * Makes a host name the store's primary address (used in links to other shops).
 */
final readonly class MakeDomainPrimary
{
    public function __construct(public int $id)
    {
    }
}
