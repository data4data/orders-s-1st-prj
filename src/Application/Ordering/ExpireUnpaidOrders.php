<?php

declare(strict_types=1);

namespace App\Application\Ordering;

/**
 * Scheduled: orders still awaiting payment after the time limit are cancelled and their stock is released.
 */
final readonly class ExpireUnpaidOrders
{
    public const MINUTES = 60;

    public function __construct(public int $minutes = self::MINUTES)
    {
    }
}
