<?php

declare(strict_types=1);

namespace App\Application\Bus;

/**
 * Dispatches a command to its single handler and returns the handler's result.
 * Commands change state; each runs in one database transaction.
 */
interface CommandBusInterface
{
    public function dispatch(object $command): mixed;
}
