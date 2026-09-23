<?php

declare(strict_types=1);

namespace App\Infrastructure\Payment;

use App\Application\Payment\AbstractPaymentGateway;
use App\Application\Payment\CheckoutSession;
use App\Application\Payment\InvalidWebhookException;
use App\Application\Payment\PaymentEvent;
use App\Application\Payment\PaymentEventType;
use App\Application\Payment\PaymentRequest;
use App\Application\Payment\RefundRequest;
use App\Application\Payment\RefundResult;
use App\Application\Payment\WebhookRequest;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Local stand-in for a real payment provider (decision #11). The "gateway page" is served by this
 * app (/fake-gateway/{reference}) from a signed URL, so no state is kept; its Pay / Fail buttons
 * produce a signed webhook exactly like a real provider would.
 */
#[AsTaggedItem('fake')]
final class FakeGateway extends AbstractPaymentGateway
{
    public const SIGNATURE_HEADER = 'X-Fake-Signature';

    public function __construct(
        #[Autowire('%env(FAKE_GATEWAY_SECRET)%')]
        string $webhookSecret,
        private readonly UrlGeneratorInterface $urls,
        private readonly UriSigner $uriSigner,
    ) {
        parent::__construct($webhookSecret);
    }

    public function code(): string
    {
        return 'fake';
    }

    public function createCheckoutSession(PaymentRequest $request): CheckoutSession
    {
        $reference = 'fake_'.bin2hex(random_bytes(12));
        $url = $this->urls->generate('fake_gateway_page', [
            'reference' => $reference,
            'amount' => $request->amount->amount,
            'currency' => $request->amount->currency,
            'order' => $request->orderNumber,
            'return' => $request->returnUrl,
            'webhook' => $request->webhookUrl,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return new CheckoutSession($reference, $this->uriSigner->sign($url), ['orderNumber' => $request->orderNumber]);
    }

    /**
     * The webhook body the fake page sends for "Pay" (capture) or "Fail".
     */
    public function webhookPayload(string $reference, PaymentEventType $type, int $amount): string
    {
        return json_encode([
            'id' => 'evt_'.bin2hex(random_bytes(12)),
            'type' => $type->value,
            'reference' => $reference,
            'amount' => $amount,
        ], \JSON_THROW_ON_ERROR);
    }

    public function handleWebhook(WebhookRequest $request): PaymentEvent
    {
        $this->assertSigned($request, self::SIGNATURE_HEADER);
        $data = $this->decode($request);

        $type = PaymentEventType::tryFrom(\is_string($data['type'] ?? null) ? $data['type'] : '');
        if (null === $type || !\is_string($data['id'] ?? null) || !\is_string($data['reference'] ?? null)) {
            throw InvalidWebhookException::badPayload($this->code());
        }

        return new PaymentEvent($this->code(), $data['id'], $type, $data['reference'], \is_int($data['amount'] ?? null) ? $data['amount'] : null);
    }

    public function refund(RefundRequest $request): RefundResult
    {
        return new RefundResult(true, 'fake_refund_'.bin2hex(random_bytes(8)));
    }
}
