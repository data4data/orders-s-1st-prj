<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Application\Ordering\Port\ShippingMethodRepositoryInterface;
use App\Entity\ShippingMethod;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShippingMethod>
 */
final class ShippingMethodRepository extends ServiceEntityRepository implements ShippingMethodRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShippingMethod::class);
    }

    public function findActive(): array
    {
        return $this->findBy(['isActive' => true], ['position' => 'ASC', 'id' => 'ASC']);
    }

    public function findActiveByCode(string $code): ?ShippingMethod
    {
        return $this->findOneBy(['code' => $code, 'isActive' => true]);
    }
}
