<?php

declare(strict_types=1);

namespace App\Application\Payment;

/**
 * A gateway callback as it arrived: verified, stored once, then processed asynchronously.
 */
final readonly class ReceivePaymentWebhook
{
    /**
     * @param array<string, string> $headers lower-case names
     */
    public function __construct(
        public string $gatewayCode,
        public string $payload,
        public array $headers,
    ) {
    }
}
