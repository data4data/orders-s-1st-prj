<?php

declare(strict_types=1);

namespace App\Application\Ordering\Admin;

use App\Application\Exception\NotFoundException;
use App\Application\Ordering\OrderTransitions;
use App\Application\Ordering\Port\OrderRepositoryInterface;
use App\Application\Ordering\Port\PaymentRepositoryInterface;
use App\Application\Ordering\View\OrderView;
use App\Domain\Ordering\OrderState;
use App\Entity\Order;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Admin → Orders: list, detail with timeline, and the workflow buttons (only what workflow.can() allows).
 */
final readonly class OrderAdminHandler
{
    /** Transitions the admin asks to confirm first (decision #52). */
    private const DESTRUCTIVE = ['cancel', 'refund'];

    public function __construct(
        private OrderRepositoryInterface $orders,
        private PaymentRepositoryInterface $payments,
        private OrderTransitions $transitions,
    ) {
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, page: int, perPage: int, states: list<array{value: string, label: string}>}
     */
    #[AsMessageHandler(bus: 'query.bus')]
    public function list(ListAdminOrders $query): array
    {
        $page = max(1, $query->page);
        $state = null !== $query->state && null !== OrderState::tryFrom($query->state) ? $query->state : null;
        $result = $this->orders->adminPage($state, $query->q, $page, 20);

        return [
            'items' => array_map(fn (Order $order) => $this->row($order), $result['items']),
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 20,
            'states' => array_values(array_map(
                static fn (OrderState $s) => ['value' => $s->value, 'label' => $s->label()],
                array_filter(OrderState::cases(), static fn (OrderState $s) => OrderState::Draft !== $s),
            )),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[AsMessageHandler(bus: 'query.bus')]
    public function detail(GetAdminOrder $query): array
    {
        return $this->detailOf($this->order($query->id));
    }

    /**
     * @return array<string, mixed>
     */
    #[AsMessageHandler(bus: 'command.bus')]
    public function transition(ApplyOrderTransition $command): array
    {
        $order = $this->order($command->id);
        if (null !== $command->version && $command->version !== $order->getVersion()) {
            throw new OptimisticLockException('This order was changed in the meantime. Reload to see the latest version.', $order);
        }
        $this->transitions->apply($order, $command->transition, $command->comment);

        return $this->detailOf($order);
    }

    private function order(string $id): Order
    {
        $order = Uuid::isValid($id) ? $this->orders->findByPublicId(Uuid::fromString($id)) : null;

        return null !== $order && !$order->isDraft() ? $order : throw NotFoundException::of('Order', $id);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Order $order): array
    {
        return [
            'id' => $order->getPublicId()->toRfc4122(),
            'number' => $order->getOrderNumber(),
            'placedAt' => $order->getPlacedAt()?->format(\DATE_ATOM),
            'customer' => $order->getBillingAddress()->fullName(),
            'company' => $order->getBillingAddress()->toArray()['company'],
            'email' => $order->getCustomerEmail(),
            'guest' => null === $order->getCustomer(),
            'state' => $order->getState(),
            'stateLabel' => $order->state()->label(),
            'badge' => $order->state()->badge(),
            'itemCount' => $order->itemCount(),
            'totalGross' => $order->getTotalGross(),
            'currency' => $order->getCurrencyCode(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailOf(Order $order): array
    {
        $payments = $this->payments->forOrder($order);

        return [
            ...$this->row($order),
            'version' => $order->getVersion(),
            'order' => OrderView::from($order, end($payments) ?: null),
            'payments' => array_map(static fn ($p) => [
                'id' => $p->getPublicId()->toRfc4122(),
                'gateway' => $p->getGatewayCode(),
                'state' => $p->getState(),
                'amount' => $p->getAmount(),
                'refunded' => $p->refundedAmount(),
                'reference' => $p->getExternalReference(),
                'createdAt' => $p->getCreatedAt()->format(\DATE_ATOM),
            ], $payments),
            'history' => array_map(static fn ($h) => [
                'transition' => $h->getTransition(),
                'from' => $h->getFromState(),
                'to' => $h->getToState(),
                'toLabel' => OrderState::from($h->getToState())->label(),
                'actorType' => $h->getActorType()->value,
                'actorName' => $h->getActorName(),
                'comment' => $h->getComment(),
                'at' => $h->getCreatedAt()->format(\DATE_ATOM),
            ], $this->orders->history($order)),
            'transitions' => array_map(static fn (string $name) => ['name' => $name, 'destructive' => \in_array($name, self::DESTRUCTIVE, true)], $this->transitions->available($order)),
        ];
    }
}
