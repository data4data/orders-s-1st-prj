<?php

declare(strict_types=1);

namespace App\Infrastructure\Fixtures;

use App\Application\Ordering\OrderNumberGeneratorInterface;
use App\Application\Ordering\OrderPricer;
use App\Application\Ordering\OrderTransitions;
use App\Application\Payment\PaymentStarter;
use App\Domain\Shared\Quantity;
use App\Entity\Coupon;
use App\Entity\Customer;
use App\Entity\Embeddable\PostalAddress;
use App\Entity\Order;
use App\Entity\ProductVariant;
use App\Entity\ShippingMethod;
use App\Entity\StaffUser;
use App\Entity\Store;
use App\Infrastructure\Tenancy\TenantContext;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Demo orders through the real application services (pricing, stock, workflows, payments,
 * refunds), so every order, payment and history row is one the shop could have produced.
 * Staff steps run as the demo manager; afterwards the dates are spread over the last weeks.
 */
final readonly class DemoOrderBuilder
{
    /** Target place => the transitions after `checkout` that lead there. */
    private const PATHS = [
        'draft' => [],
        'payment_pending' => [],
        'payment_failed' => ['@fail'],
        'cancelled_unpaid' => ['cancel'],
        'paid' => ['@capture', 'pay'],
        'processing' => ['@capture', 'pay', 'start_processing'],
        'shipped' => ['@capture', 'pay', 'start_processing', 'ship'],
        'delivered' => ['@capture', 'pay', 'start_processing', 'ship', 'deliver'],
        'cancelled_paid' => ['@capture', 'pay', 'cancel'],
        'refunded' => ['@capture', 'pay', 'start_processing', 'ship', 'deliver', 'refund'],
    ];

    private const COMMENTS = [
        'cancel' => 'Customer asked to cancel by phone.',
        'refund' => 'Wrong viscosity delivered; customer returned the goods.',
        'ship' => 'PostNL track & trace sent.',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TenantContext $tenantContext,
        private OrderPricer $pricer,
        private OrderNumberGeneratorInterface $orderNumbers,
        private OrderTransitions $transitions,
        #[Target('order')]
        private WorkflowInterface $orderWorkflow,
        #[Target('payment')]
        private WorkflowInterface $paymentWorkflow,
        private PaymentStarter $paymentStarter,
        private TokenStorageInterface $tokens,
        private RequestContext $requestContext,
        private Connection $connection,
    ) {
    }

    /**
     * @param list<array{target: string, customer?: Customer, guest?: array{email: string, address: array<string, string>}, items: array<string, int>, shipping?: string, coupon?: string, daysAgo: float}> $orders
     */
    public function build(Store $store, string $host, StaffUser $staff, array $orders): void
    {
        $this->requestContext->setHost($host)->setScheme('https');
        $notifications = $store->getNotifications();
        // No demo emails: the worker would send them all to Mailpit on its first start.
        $store->changeNotifications(array_map(static fn () => false, $notifications));
        $this->entityManager->flush();

        $this->tenantContext->runAsStore($store, function () use ($store, $staff, $orders): void {
            foreach ($orders as $plan) {
                $this->order($store, $staff, $plan);
            }
        });

        $store->changeNotifications($notifications);
        $this->entityManager->flush();
    }

    /**
     * @param array{target: string, customer?: Customer, guest?: array{email: string, address: array<string, string>}, items: array<string, int>, shipping?: string, coupon?: string, daysAgo: float} $plan
     */
    private function order(Store $store, StaffUser $staff, array $plan): void
    {
        $order = new Order($store->getCurrencyCode());
        $order->assignCustomer($plan['customer'] ?? null);
        foreach ($plan['items'] as $sku => $quantity) {
            $variant = $this->entityManager->getRepository(ProductVariant::class)->findOneBy(['sku' => $sku]) ?? throw new \LogicException(sprintf('Unknown demo SKU "%s".', $sku));
            $order->add($variant, Quantity::of($quantity));
        }
        if (isset($plan['coupon'])) {
            $order->applyCoupon($this->entityManager->getRepository(Coupon::class)->findOneBy(['code' => $plan['coupon']]));
        }
        $this->entityManager->persist($order);
        if ('draft' === $plan['target']) {
            $this->entityManager->flush();

            return;
        }

        [$email, $billing, $shipping] = $this->addresses($plan);
        $method = $this->entityManager->getRepository(ShippingMethod::class)->findOneBy(['code' => $plan['shipping'] ?? 'standard'])
            ?? throw new \LogicException('Unknown demo shipping method.');
        $priced = $this->pricer->price($store, $order, $method, $shipping->getCountryCode());
        foreach ($order->getItems() as $item) {
            $variant = $item->getVariant() ?? throw new \LogicException('Demo lines always have a variant.');
            $variant->applyStock($variant->stock()->reserve(Quantity::of($item->getQuantity())));
            $item->snapshot($priced->lines[$variant->getPublicId()->toRfc4122()]);
        }
        $placedAt = new \DateTimeImmutable(sprintf('-%d minutes', (int) round($plan['daysAgo'] * 1440)));
        $order->snapshot($this->orderNumbers->next($store), $email, $billing, $shipping, $method, $priced->shipping?->taxRate->toString() ?? '0.00', $store->getCountry()->getCode(), $priced->totals, $placedAt);
        $order->getCoupon()?->recordUse();
        $this->orderWorkflow->apply($order, 'checkout');
        $this->entityManager->flush();
        $payment = $this->paymentStarter->start($order);

        foreach (self::PATHS[$plan['target']] as $step) {
            if (str_starts_with($step, '@')) {
                $this->paymentWorkflow->apply($payment, substr($step, 1));
                $this->entityManager->flush();
                continue;
            }
            // Payment confirmation comes from the system (webhook); everything else from staff.
            $asStaff = 'pay' !== $step && !('cancel' === $step && 'cancelled_unpaid' === $plan['target']);
            $this->tokens->setToken($asStaff ? new UsernamePasswordToken($staff, 'admin', $staff->getRoles()) : null);
            $this->transitions->apply($order, $step, self::COMMENTS[$step] ?? null);
            $this->tokens->setToken(null);
        }
        $this->entityManager->flush();
        $this->backdate($order, $placedAt);
    }

    /**
     * @param array{customer?: Customer, guest?: array{email: string, address: array<string, string>}} $plan
     *
     * @return array{string, PostalAddress, PostalAddress}
     */
    private function addresses(array $plan): array
    {
        if (isset($plan['customer'])) {
            $customer = $plan['customer'];
            $billing = $customer->getDefaultBillingAddress() ?? throw new \LogicException('Demo customers have addresses.');
            $shipping = $customer->getDefaultShippingAddress() ?? $billing;

            return [$customer->getEmail(), $billing->snapshot(), $shipping->snapshot()];
        }
        $guest = $plan['guest'] ?? throw new \LogicException('A demo order needs a customer or a guest.');
        $a = $guest['address'];
        $address = new PostalAddress($a['firstName'], $a['lastName'], $a['company'] ?? null, null, $a['street'], $a['houseNumber'], $a['postcode'], $a['city'], $a['countryCode'] ?? 'NL', null);

        return [$guest['email'], $address, $address];
    }

    /**
     * The services stamp "now"; spread the order, its payment and its history over the plan's date.
     */
    private function backdate(Order $order, \DateTimeImmutable $placedAt): void
    {
        $id = $order->getId();
        $at = $placedAt->format('Y-m-d H:i:s');
        $this->connection->executeStatement('UPDATE orders SET created_at = ?, updated_at = ? WHERE id = ?', [$at, $at, $id]);
        $this->connection->executeStatement('UPDATE payment SET created_at = ?, updated_at = ? WHERE order_id = ?', [$at, $at, $id]);
        $historyIds = $this->connection->fetchFirstColumn('SELECT id FROM order_status_history WHERE order_id = ? ORDER BY id', [$id]);
        foreach ($historyIds as $step => $historyId) {
            // checkout at the order time, then roughly half a day per step (never in the future).
            $when = min($placedAt->modify(sprintf('+%d minutes', $step * 700 + $step * 7)), new \DateTimeImmutable());
            $this->connection->executeStatement('UPDATE order_status_history SET created_at = ? WHERE id = ?', [$when->format('Y-m-d H:i:s'), $historyId]);
        }
    }
}
