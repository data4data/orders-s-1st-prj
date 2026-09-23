<?php

declare(strict_types=1);

namespace App\Application\Payment;

/**
 * Shared gateway behaviour: HMAC signatures for webhooks and decoding of JSON payloads.
 */
abstract class AbstractPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(private readonly string $webhookSecret)
    {
    }

    public function sign(string $payload): string
    {
        return 'sha256='.hash_hmac('sha256', $payload, $this->webhookSecret);
    }

    /**
     * @throws InvalidWebhookException
     */
    protected function assertSigned(WebhookRequest $request, string $header): void
    {
        $signature = $request->header($header);
        if (null === $signature || !hash_equals($this->sign($request->payload), $signature)) {
            throw InvalidWebhookException::badSignature($this->code());
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidWebhookException
     */
    protected function decode(WebhookRequest $request): array
    {
        try {
            $data = json_decode($request->payload, true, 16, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw InvalidWebhookException::badPayload($this->code());
        }

        return \is_array($data) ? $data : throw InvalidWebhookException::badPayload($this->code());
    }
}
