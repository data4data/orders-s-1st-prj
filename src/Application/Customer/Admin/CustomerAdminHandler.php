<?php

declare(strict_types=1);

namespace App\Application\Customer\Admin;

use App\Application\Content\Port\ContactMessageRepositoryInterface;
use App\Application\Customer\View\AddressView;
use App\Application\Exception\NotFoundException;
use App\Application\Ordering\Port\OrderRepositoryInterface;
use App\Domain\Ordering\OrderState;
use App\Entity\ContactMessage;
use App\Entity\Customer;
use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Admin → Customers (list, detail) and the Contact messages tab. Read-mostly screens, so the
 * queries use Doctrine directly (the tenant filter keeps them inside the selected store).
 */
final readonly class CustomerAdminHandler
{
    private const PAID = ['paid', 'processing', 'shipped', 'delivered'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrderRepositoryInterface $orders,
        private ContactMessageRepositoryInterface $messages,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, page: int, perPage: int}
     */
    #[AsMessageHandler(bus: 'query.bus')]
    public function list(ListAdminCustomers $query): array
    {
        $page = max(1, $query->page);
        $qb = $this->entityManager->createQueryBuilder()->select('c')->from(Customer::class, 'c');
        if ('' !== trim($query->q)) {
            $qb->where('c.email LIKE :q OR c.lastName LIKE :q OR c.firstName LIKE :q')->setParameter('q', '%'.trim($query->q).'%');
        }
        $total = (int) (clone $qb)->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();
        /** @var list<Customer> $customers */
        $customers = $qb->orderBy('c.createdAt', 'DESC')->addOrderBy('c.id', 'DESC')->setFirstResult(($page - 1) * 20)->setMaxResults(20)->getQuery()->getResult();

        $stats = $this->stats(array_map(static fn (Customer $c) => (int) $c->getId(), $customers));

        return [
            'items' => array_map(fn (Customer $c) => $this->row($c) + ($stats[(int) $c->getId()] ?? ['orders' => 0, 'spent' => 0]), $customers),
            'total' => $total,
            'page' => $page,
            'perPage' => 20,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[AsMessageHandler(bus: 'query.bus')]
    public function detail(GetAdminCustomer $query): array
    {
        $customer = Uuid::isValid($query->id) ? $this->entityManager->getRepository(Customer::class)->findOneBy(['publicId' => Uuid::fromString($query->id)]) : null;
        if (null === $customer) {
            throw NotFoundException::of('Customer', $query->id);
        }
        $orders = $this->orders->placedOrdersOf($customer, 1, 50)['items'];

        return $this->row($customer) + ($this->stats([(int) $customer->getId()])[(int) $customer->getId()] ?? ['orders' => 0, 'spent' => 0]) + [
            'phone' => $customer->getPhone(),
            'addresses' => array_values(array_map(AddressView::from(...), $customer->getAddresses()->toArray())),
            'orderList' => array_map(static fn (Order $o) => [
                'id' => $o->getPublicId()->toRfc4122(),
                'number' => $o->getOrderNumber(),
                'placedAt' => $o->getPlacedAt()?->format(\DATE_ATOM),
                'state' => $o->getState(),
                'stateLabel' => $o->state()->label(),
                'badge' => $o->state()->badge(),
                'totalGross' => $o->getTotalGross(),
                'currency' => $o->getCurrencyCode(),
            ], $orders),
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, unread: int, page: int, perPage: int}
     */
    #[AsMessageHandler(bus: 'query.bus')]
    public function messages(ListContactMessages $query): array
    {
        $page = max(1, $query->page);
        $result = $this->messages->page($query->unreadOnly, $page, 20);

        return [
            'items' => array_map(static fn (ContactMessage $m) => [
                'id' => $m->getId(),
                'name' => $m->getName(),
                'email' => $m->getEmail(),
                'subject' => $m->getSubject(),
                'orderNumber' => $m->getOrderNumber(),
                'message' => $m->getMessage(),
                'read' => $m->isRead(),
                'createdAt' => $m->getCreatedAt()->format(\DATE_ATOM),
            ], $result['items']),
            'total' => $result['total'],
            'unread' => $result['unread'],
            'page' => $page,
            'perPage' => 20,
        ];
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function markRead(MarkContactMessageRead $command): void
    {
        $message = $this->messages->find($command->id) ?? throw NotFoundException::of('Contact message', (string) $command->id);
        $message->markRead($this->clock->now());
        $this->messages->save($message);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Customer $customer): array
    {
        $billing = $customer->getDefaultBillingAddress();

        return [
            'id' => $customer->getPublicId()->toRfc4122(),
            'name' => trim($customer->getFirstName().' '.$customer->getLastName()),
            'email' => $customer->getEmail(),
            'company' => $billing?->getCompany(),
            'city' => $billing?->getCity(),
            'createdAt' => $customer->getCreatedAt()->format(\DATE_ATOM),
        ];
    }

    /**
     * @param list<int> $customerIds
     *
     * @return array<int, array{orders: int, spent: int}>
     */
    private function stats(array $customerIds): array
    {
        if ([] === $customerIds) {
            return [];
        }
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(o.customer) AS customer, COUNT(o.id) AS orders, COALESCE(SUM(CASE WHEN o.state IN (:paid) THEN o.totalGross ELSE 0 END), 0) AS spent')
            ->from(Order::class, 'o')
            ->where('o.customer IN (:ids)')->andWhere('o.state <> :draft')
            ->setParameter('ids', $customerIds)->setParameter('paid', self::PAID)->setParameter('draft', OrderState::Draft->value)
            ->groupBy('o.customer')->getQuery()->getArrayResult();

        $stats = [];
        foreach ($rows as $row) {
            $stats[(int) $row['customer']] = ['orders' => (int) $row['orders'], 'spent' => (int) $row['spent']];
        }

        return $stats;
    }
}
