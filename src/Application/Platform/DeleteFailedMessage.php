<?php

declare(strict_types=1);

namespace App\Application\Platform;

/**
 * Removes a failed message.
 */
final readonly class DeleteFailedMessage
{
    public function __construct(public int $id)
    {
    }
}
