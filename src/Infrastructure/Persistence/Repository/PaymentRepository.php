<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Application\Ordering\Port\PaymentRepositoryInterface;
use App\Entity\Order;
use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
final class PaymentRepository extends ServiceEntityRepository implements PaymentRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function latestFor(Order $order): ?Payment
    {
        return $this->findOneBy(['order' => $order], ['id' => 'DESC']);
    }

    public function save(Payment $payment): void
    {
        $this->getEntityManager()->persist($payment);
        $this->getEntityManager()->flush();
    }
}
