<?php

declare(strict_types=1);

namespace App\Application\Payment;

/**
 * Async: applies a stored webhook event (payment transition, then order `pay` on capture).
 */
final readonly class ProcessPaymentWebhook
{
    public function __construct(public int $eventId)
    {
    }
}
