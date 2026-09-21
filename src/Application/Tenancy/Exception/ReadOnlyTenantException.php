<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Exception;

/**
 * Thrown when something tries to write while the super-admin views "All stores".
 */
final class ReadOnlyTenantException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The "All stores" view is read-only. Select a single store to make changes.');
    }
}
