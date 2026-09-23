<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Carries the active store with a message, so async handlers run for the same store.
 */
final readonly class StoreStamp implements StampInterface
{
    public function __construct(public int $storeId)
    {
    }
}
