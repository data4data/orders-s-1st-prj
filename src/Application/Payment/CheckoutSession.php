<?php

declare(strict_types=1);

namespace App\Application\Payment;

/**
 * The gateway's answer to createCheckoutSession(): its own reference and the page to redirect to.
 */
final readonly class CheckoutSession
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $externalReference,
        public string $redirectUrl,
        public array $metadata = [],
    ) {
    }
}
