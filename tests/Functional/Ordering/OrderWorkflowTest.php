<?php

declare(strict_types=1);

namespace App\Tests\Functional\Ordering;

use App\Application\Payment\PaymentEventType;
use App\Application\Payment\ProcessPaymentWebhook;
use App\Domain\Tenancy\StoreRole;
use App\Entity\Order;
use App\Entity\OrderStatusHistory;
use App\Entity\Payment;
use App\Entity\PaymentWebhookEvent;
use App\Entity\ProductVariant;
use App\Entity\Store;
use App\Infrastructure\Fixtures\CatalogBuilder;
use App\Infrastructure\Fixtures\CheckoutBuilder;
use App\Infrastructure\Fixtures\Factory\StaffUserFactory;
use App\Infrastructure\Fixtures\Factory\StoreMembershipFactory;
use App\Infrastructure\Payment\FakeGateway;
use App\Tests\Support\AsyncMessages;
use App\Tests\Support\JsonApi;
use App\Tests\Support\ShopFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * The order and payment state machines end to end: webhook → pay → fulfilment, cancel and refund,
 * with the stock invariants of architecture.md §4 on every path.
 */
final class OrderWorkflowTest extends WebTestCase
{
    use Factories;
    use JsonApi;
    use ResetDatabase;

    private const AUTO = ShopFixtures::AUTO;
    private const ADMIN = 'https://admin.shop.test';

    private KernelBrowser $client;
    private Store $auto;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        // Keep one container for the whole test, so queued async messages survive between requests.
        $this->client->disableReboot();
        $this->auto = ShopFixtures::create(self::getContainer()->get(CatalogBuilder::class), self::getContainer()->get(CheckoutBuilder::class))['auto'];
    }

    public function testThePaymentWebhookMarksTheOrderPaidAndTakesTheStockOut(): void
    {
        $placed = $this->placeOrder(2);
        self::assertSame([60, 2], $this->stock());

        $payload = $this->capture($placed['orderId']);
        // Stored and queued, not processed yet.
        self::assertSame('payment_pending', $this->order($placed['orderId'])->getState());

        self::assertSame(1, $this->runWebhookWorker());
        // Payment confirmation email to the customer, queued on the async transport by the worker run.
        self::assertSame(sprintf('Order %s confirmed: payment received', $placed['orderNumber']), $this->queuedEmailSubject());
        $order = $this->order($placed['orderId']);
        self::assertSame('paid', $order->getState());
        self::assertSame([58, 0], $this->stock());
        self::assertSame('captured', $this->find(Payment::class, ['order' => $order])->getState());
        self::assertSame('payment captured, order paid', $this->find(PaymentWebhookEvent::class, [])->getResult());

        // A repeated delivery is recognised and ignored.
        $this->webhook($payload, 200);
        self::assertCount(1, $this->em()->getRepository(PaymentWebhookEvent::class)->findAll());

        $history = array_map(static fn (OrderStatusHistory $h) => [$h->getTransition(), $h->getToState(), $h->getActorType()->value], $this->em()->getRepository(OrderStatusHistory::class)->findBy(['order' => $order], ['id' => 'ASC']));
        self::assertSame([['checkout', 'payment_pending', 'system'], ['pay', 'paid', 'system']], $history);
    }

    public function testAFailedPaymentKeepsTheOrderWaitingAndTheCustomerCanTryAgain(): void
    {
        $placed = $this->placeOrder(1);
        $gateway = self::getContainer()->get(FakeGateway::class);
        $first = $this->find(Payment::class, ['order' => $this->order($placed['orderId'])]);
        $this->webhook($gateway->webhookPayload((string) $first->getExternalReference(), PaymentEventType::Failed, 4995), 202);
        $this->runWebhookWorker();

        $confirmation = $this->api('GET', self::AUTO.'/api/orders/'.$placed['orderId']);
        self::assertSame(['payment_pending', 'failed', null], [$confirmation['state'], $confirmation['paymentState'], $confirmation['paymentUrl']]);

        $retry = $this->api('POST', self::AUTO.'/api/orders/'.$placed['orderId'].'/payment');
        self::assertStringContainsString('/fake-gateway/fake_', $retry['redirectUrl']);
        self::assertNotSame($first->getCheckoutUrl(), $retry['redirectUrl']);
        // Asking again while that attempt is open reuses it.
        self::assertSame($retry['redirectUrl'], $this->api('POST', self::AUTO.'/api/orders/'.$placed['orderId'].'/payment')['redirectUrl']);
    }

    public function testTheCustomerCancelsAnUnpaidOrderAndTheReservationIsReleased(): void
    {
        $placed = $this->placeOrder(2);
        $cancelled = $this->api('POST', self::AUTO.'/api/orders/'.$placed['orderId'].'/cancel');

        self::assertSame('cancelled', $cancelled['state']);
        self::assertSame([60, 0], $this->stock());
        self::assertSame('cancelled', $this->find(Payment::class, ['order' => $this->order($placed['orderId'])])->getState());
        // Cancelling twice is refused with a clear message.
        self::assertSame('The order cannot be cancelled now (it is cancelled).', $this->api('POST', self::AUTO.'/api/orders/'.$placed['orderId'].'/cancel', expected: 422)['detail']);
    }

    public function testStaffFulfilAPaidOrderStepByStep(): void
    {
        $placed = $this->paidOrder(2);
        $this->loginStaff();

        $detail = $this->api('GET', self::ADMIN.'/api/admin/orders/'.$placed['orderId']);
        self::assertSame(['start_processing', 'cancel'], array_column($detail['transitions'], 'name'));
        self::assertTrue($detail['transitions'][1]['destructive']);
        self::assertSame(['checkout', 'pay'], array_column($detail['history'], 'transition'));

        // An illegal transition is refused: no edge from paid to shipped.
        self::assertSame('The order cannot be shipped now (it is paid).', $this->api('POST', self::ADMIN.'/api/admin/orders/'.$placed['orderId'].'/transitions', ['transition' => 'ship'], 422)['detail']);
        // A stale screen is refused.
        $this->api('POST', self::ADMIN.'/api/admin/orders/'.$placed['orderId'].'/transitions', ['transition' => 'start_processing', 'version' => $detail['version'] - 1], 409);

        foreach (['start_processing' => 'processing', 'ship' => 'shipped', 'deliver' => 'delivered'] as $transition => $state) {
            $detail = $this->api('POST', self::ADMIN.'/api/admin/orders/'.$placed['orderId'].'/transitions', ['transition' => $transition, 'comment' => 'Step '.$transition, 'version' => $detail['version']]);
            self::assertSame($state, $detail['state']);
        }
        self::assertSame(['refund'], array_column($detail['transitions'], 'name'));
        self::assertSame(['staff', 'Step deliver'], [$detail['history'][4]['actorType'], $detail['history'][4]['comment']]);
        self::assertSame([58, 0], $this->stock());

        // Refund after delivery: money back, goods stay out of stock (returns come later).
        $detail = $this->api('POST', self::ADMIN.'/api/admin/orders/'.$placed['orderId'].'/transitions', ['transition' => 'refund', 'comment' => 'Damaged on arrival']);
        self::assertSame('refunded', $detail['state']);
        self::assertSame(['refunded', 11489], [$detail['payments'][0]['state'], $detail['payments'][0]['refunded']]);
        self::assertSame([58, 0], $this->stock());
    }

    public function testCancellingAPaidOrderRefundsFirstAndRestocks(): void
    {
        $placed = $this->paidOrder(2);
        $this->loginStaff();

        $detail = $this->api('POST', self::ADMIN.'/api/admin/orders/'.$placed['orderId'].'/transitions', ['transition' => 'cancel', 'comment' => 'Customer called']);

        self::assertSame('cancelled', $detail['state']);
        self::assertSame(['refunded', 11489], [$detail['payments'][0]['state'], $detail['payments'][0]['refunded']]);
        self::assertSame([60, 0], $this->stock());
    }

    public function testCustomersCannotFulfilAndPaidOrdersAreStaffOnly(): void
    {
        $placed = $this->paidOrder(1);

        // The storefront cancel is for unpaid orders only.
        self::assertSame('Only staff of this store can do this.', $this->api('POST', self::AUTO.'/api/orders/'.$placed['orderId'].'/cancel', expected: 422)['detail']);
        self::assertSame('paid', $this->order($placed['orderId'])->getState());
    }

    public function testStaleUnpaidOrdersExpire(): void
    {
        $placed = $this->placeOrder(2);
        $this->em()->createQuery('UPDATE '.Order::class.' o SET o.placedAt = :old')->setParameter('old', new \DateTimeImmutable('-2 hours'))->execute();

        $cancelled = self::getContainer()->get(\App\Application\Bus\CommandBusInterface::class)->dispatch(new \App\Application\Ordering\ExpireUnpaidOrders(60));

        self::assertSame(1, $cancelled);
        $order = $this->order($placed['orderId']);
        self::assertSame('cancelled', $order->getState());
        self::assertSame([60, 0], $this->stock());
        $last = $this->em()->getRepository(OrderStatusHistory::class)->findOneBy(['order' => $order], ['id' => 'DESC']);
        self::assertSame(['system', 'Payment not received within 60 minutes.'], [$last?->getActorType()->value, $last?->getComment()]);
    }

    public function testTheDashboardSummarisesTheStore(): void
    {
        $this->paidOrder(2);
        $this->placeOrder(1);
        $this->loginStaff();

        $dashboard = $this->api('GET', self::ADMIN.'/api/admin/dashboard');

        self::assertSame(['revenue' => 11489, 'paidOrders' => 1, 'placedOrders' => 2, 'awaitingPayment' => 1, 'toFulfil' => 1, 'averageOrder' => 11489], $dashboard['kpis']);
        self::assertSame(['payment_pending' => 1, 'paid' => 1], array_filter(array_column($dashboard['ordersByState'], 'count', 'state')));
        self::assertCount(14, $dashboard['revenueByDay']);
        self::assertSame(11489, end($dashboard['revenueByDay'])['revenue']);
        self::assertContains('SP530-208', array_column($dashboard['lowStock'], 'sku'));
        self::assertCount(2, $dashboard['latestOrders']);
    }

    /**
     * @return array{orderId: string, orderNumber: string, redirectUrl: string}
     */
    private function placeOrder(int $quantity): array
    {
        $this->client->request('GET', self::AUTO.'/api/products/synth-pro-5w-30');
        $variant = json_decode((string) $this->client->getResponse()->getContent(), true)['variants'][1]['publicId'];
        $this->api('POST', self::AUTO.'/api/cart/lines', ['variantId' => $variant, 'quantity' => $quantity]);

        $placed = $this->api('POST', self::AUTO.'/api/checkout', [
            'email' => 'piet@example.test',
            'billing' => ['firstName' => 'Piet', 'lastName' => 'Jansen', 'street' => 'Damrak', 'houseNumber' => '1', 'postcode' => '1012 LG', 'city' => 'Amsterdam', 'countryCode' => 'NL'],
            'shippingSameAsBilling' => true,
            'shippingMethod' => 'express',
            'acceptTerms' => true,
        ], 201);
        self::assertIsString($placed['orderId'] ?? null);
        self::assertIsString($placed['orderNumber'] ?? null);
        self::assertIsString($placed['redirectUrl'] ?? null);

        return ['orderId' => $placed['orderId'], 'orderNumber' => $placed['orderNumber'], 'redirectUrl' => $placed['redirectUrl']];
    }

    private function runWebhookWorker(): int
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return AsyncMessages::consume($transport, self::getContainer()->get('messenger.routable_message_bus'), ProcessPaymentWebhook::class);
    }

    /**
     * @return array{orderId: string, orderNumber: string, redirectUrl: string}
     */
    private function paidOrder(int $quantity): array
    {
        $placed = $this->placeOrder($quantity);
        $this->capture($placed['orderId']);
        $this->runWebhookWorker();

        return $placed;
    }

    private function capture(string $orderId): string
    {
        $payment = $this->find(Payment::class, ['order' => $this->order($orderId)]);
        $payload = self::getContainer()->get(FakeGateway::class)->webhookPayload((string) $payment->getExternalReference(), PaymentEventType::Captured, $payment->getAmount());
        $this->webhook($payload, 202);

        return $payload;
    }

    private function queuedEmailSubject(): ?string
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        foreach ($transport->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof \Symfony\Component\Mailer\Messenger\SendEmailMessage && $message->getMessage() instanceof \Symfony\Component\Mime\Email) {
                return $message->getMessage()->getSubject();
            }
        }

        return null;
    }

    private function webhook(string $payload, int $expected): void
    {
        $this->client->request('POST', self::AUTO.'/webhooks/payment/fake', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FAKE_SIGNATURE' => self::getContainer()->get(FakeGateway::class)->sign($payload),
        ], content: $payload);
        self::assertResponseStatusCodeSame($expected);
    }

    private function loginStaff(): void
    {
        $staff = StaffUserFactory::createOne();
        StoreMembershipFactory::createOne(['staffUser' => $staff, 'store' => $this->auto, 'role' => StoreRole::Manager]);
        $this->client->getCookieJar()->clear();
        $this->client->loginUser($staff, 'admin');
        $this->csrfToken = '';
        $this->api('PUT', self::ADMIN.'/api/admin/stores/current', ['store' => $this->auto->getPublicId()->toRfc4122()], 204);
    }

    /** @return array{int, int} on hand and reserved of the 5 L Synth Pro */
    private function stock(): array
    {
        $variant = $this->find(ProductVariant::class, ['sku' => 'SP530-5']);

        return [$variant->getOnHand(), $variant->getReserved()];
    }

    private function order(string $id): Order
    {
        return $this->find(Order::class, ['publicId' => Uuid::fromString($id)]);
    }

    /**
     * @template T of object
     *
     * @param class-string<T>      $class
     * @param array<string, mixed> $criteria
     *
     * @return T
     */
    private function find(string $class, array $criteria): object
    {
        $this->em()->clear();
        if ($this->em()->getFilters()->isEnabled('tenant')) {
            $this->em()->getFilters()->disable('tenant');
        }

        return $this->em()->getRepository($class)->findOneBy($criteria) ?? throw new \LogicException($class.' not found');
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
