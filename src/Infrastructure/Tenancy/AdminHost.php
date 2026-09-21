<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy;

/**
 * The admin runs on its own host, e.g. admin.shop.test (decision #29). Store hosts may not
 * start with "admin." (enforced in StoreDomain).
 */
final class AdminHost
{
    public static function matches(string $host): bool
    {
        return str_starts_with(strtolower($host), 'admin.');
    }
}
