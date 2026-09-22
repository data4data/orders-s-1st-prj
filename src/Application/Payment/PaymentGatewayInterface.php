<?php

declare(strict_types=1);

namespace App\Application\Payment;

/**
 * A payment provider (architecture.md §7). Add a class implementing this interface (or extending
 * AbstractPaymentGateway) and set store.payment_gateway_code to its code(); nothing else changes.
 */
interface PaymentGatewayInterface
{
    /** Stored in store.payment_gateway_code and payment.gateway_code, e.g. "fake". */
    public function code(): string;

    /** Starts a payment and tells where to send the customer. */
    public function createCheckoutSession(PaymentRequest $request): CheckoutSession;

    /**
     * Verifies and translates a gateway callback.
     *
     * @throws InvalidWebhookException when the signature or payload is wrong
     */
    public function handleWebhook(WebhookRequest $request): PaymentEvent;

    /** Sends (part of) a captured payment back to the customer. */
    public function refund(RefundRequest $request): RefundResult;
}
