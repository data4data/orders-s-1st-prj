<?php

declare(strict_types=1);

namespace App\Application\Payment;

/**
 * A raw gateway callback, independent of HttpFoundation.
 */
final readonly class WebhookRequest
{
    /**
     * @param array<string, string> $headers lower-case header names
     */
    public function __construct(
        public string $payload,
        public array $headers,
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
