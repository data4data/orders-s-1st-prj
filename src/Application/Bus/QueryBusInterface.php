<?php

declare(strict_types=1);

namespace App\Application\Bus;

/**
 * Dispatches a read-only query to its single handler and returns the result.
 */
interface QueryBusInterface
{
    public function ask(object $query): mixed;
}
