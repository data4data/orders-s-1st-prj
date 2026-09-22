<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Application\Ordering\Port\OrderRepositoryInterface;
use App\Domain\Ordering\OrderState;
use App\Entity\Customer;
use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Order>
 */
final class OrderRepository extends ServiceEntityRepository implements OrderRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function findByPublicId(Uuid $publicId): ?Order
    {
        return $this->findOneBy(['publicId' => $publicId]);
    }

    public function findDraftByPublicId(Uuid $publicId): ?Order
    {
        return $this->findOneBy(['publicId' => $publicId, 'state' => OrderState::Draft->value]);
    }

    public function findDraftOfCustomer(Customer $customer): ?Order
    {
        return $this->findOneBy(['customer' => $customer, 'state' => OrderState::Draft->value], ['id' => 'DESC']);
    }

    public function placedOrdersOf(Customer $customer, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('o')
            ->where('o.customer = :customer')->andWhere('o.state <> :draft')
            ->setParameter('customer', $customer)->setParameter('draft', OrderState::Draft->value);
        $total = (int) (clone $qb)->select('COUNT(o.id)')->getQuery()->getSingleScalarResult();
        /** @var list<Order> $items */
        $items = $qb->orderBy('o.placedAt', 'DESC')->addOrderBy('o.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();

        return ['items' => $items, 'total' => $total];
    }

    public function save(Order $order): void
    {
        $this->getEntityManager()->persist($order);
        $this->getEntityManager()->flush();
    }

    public function remove(Order $order): void
    {
        $this->getEntityManager()->remove($order);
        $this->getEntityManager()->flush();
    }
}
