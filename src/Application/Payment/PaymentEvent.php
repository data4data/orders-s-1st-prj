<?php

declare(strict_types=1);

namespace App\Application\Payment;

/**
 * A verified gateway event. `eventId` is the idempotency key (payment_webhook_event).
 */
final readonly class PaymentEvent
{
    public function __construct(
        public string $gatewayCode,
        public string $eventId,
        public PaymentEventType $type,
        public string $externalReference,
        public ?int $amount = null,
    ) {
    }
}
