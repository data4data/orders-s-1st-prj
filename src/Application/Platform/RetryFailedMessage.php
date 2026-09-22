<?php

declare(strict_types=1);

namespace App\Application\Platform;

/**
 * Puts a failed message back on its queue.
 */
final readonly class RetryFailedMessage
{
    public function __construct(public int $id)
    {
    }
}
