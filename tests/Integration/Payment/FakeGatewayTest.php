<?php

declare(strict_types=1);

namespace App\Tests\Integration\Payment;

use App\Application\Payment\InvalidWebhookException;
use App\Application\Payment\PaymentEventType;
use App\Application\Payment\PaymentGatewayRegistry;
use App\Application\Payment\PaymentRequest;
use App\Application\Payment\RefundRequest;
use App\Application\Payment\WebhookRequest;
use App\Domain\Money\Money;
use App\Infrastructure\Fixtures\Factory\StoreDomainFactory;
use App\Infrastructure\Fixtures\Factory\StoreFactory;
use App\Infrastructure\Payment\FakeGateway;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * The payment port with its only implementation (decision #11): the registry finds the gateway by
 * code, sessions point to the signed fake page, webhooks must carry a valid signature.
 */
final class FakeGatewayTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        // The gateway page and the webhook are served on the shop's own host, like during a real checkout.
        StoreDomainFactory::createOne(['store' => StoreFactory::createOne(['code' => 'myoils-auto']), 'host' => 'myoils-auto.shop.test']);
        self::getContainer()->get(RouterInterface::class)->getContext()->setHost('myoils-auto.shop.test')->setScheme('https');
    }

    public function testCheckoutSessionAndSignedPage(): void
    {
        $client = $this->client;
        $gateway = self::getContainer()->get(PaymentGatewayRegistry::class)->get('fake');
        self::assertInstanceOf(FakeGateway::class, $gateway);

        $session = $gateway->createCheckoutSession(new PaymentRequest('p1', 'AUTO-000042', Money::of(9689, 'EUR'), 'a@b.test', 'https://myoils-auto.shop.test/order/x', 'https://myoils-auto.shop.test/webhooks/payment/fake'));
        self::assertMatchesRegularExpression('/^fake_[0-9a-f]{24}$/', $session->externalReference);

        $client->request('GET', $session->redirectUrl);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-testid="fake-amount"]', '€96.89');

        // Tampering with the amount breaks the signature.
        $client->request('GET', str_replace('amount=9689', 'amount=1', $session->redirectUrl));
        self::assertResponseStatusCodeSame(403);

        $client->request('GET', $session->redirectUrl);
        $client->submitForm('Pay now');
        self::assertResponseRedirects('https://myoils-auto.shop.test/order/x?payment=paid');
    }

    public function testWebhooksMustBeSigned(): void
    {
        $client = $this->client;
        $gateway = self::getContainer()->get(FakeGateway::class);
        $payload = $gateway->webhookPayload('fake_abc', PaymentEventType::Captured, 9689);

        $event = $gateway->handleWebhook(new WebhookRequest($payload, ['x-fake-signature' => $gateway->sign($payload)]));
        self::assertSame([PaymentEventType::Captured, 'fake_abc', 9689, 'fake'], [$event->type, $event->externalReference, $event->amount, $event->gatewayCode]);
        self::assertStringStartsWith('evt_', $event->eventId);

        $client->request('POST', 'https://myoils-auto.shop.test/webhooks/payment/fake', server: ['HTTP_X_FAKE_SIGNATURE' => $gateway->sign($payload), 'CONTENT_TYPE' => 'application/json'], content: $payload);
        self::assertResponseStatusCodeSame(202);
        $client->request('POST', 'https://myoils-auto.shop.test/webhooks/payment/fake', server: ['HTTP_X_FAKE_SIGNATURE' => 'sha256=forged'], content: $payload);
        self::assertResponseStatusCodeSame(400);
        $client->request('POST', 'https://myoils-auto.shop.test/webhooks/payment/stripe', content: $payload);
        self::assertResponseStatusCodeSame(404);

        $this->expectException(InvalidWebhookException::class);
        $gateway->handleWebhook(new WebhookRequest('{"id":"evt_1"}', ['x-fake-signature' => $gateway->sign('{"id":"evt_1"}')]));
    }

    public function testRefunds(): void
    {
        $gateway = self::getContainer()->get(FakeGateway::class);

        self::assertTrue($gateway->refund(new RefundRequest('fake_abc', Money::of(500, 'EUR'), 'damaged'))->succeeded);
    }
}
