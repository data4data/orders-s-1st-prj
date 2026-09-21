<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Entity\StoreDomain;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StoreDomain>
 */
final class StoreDomainRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StoreDomain::class);
    }

    public function findOneByHost(string $host): ?StoreDomain
    {
        return $this->createQueryBuilder('d')
            ->addSelect('s')
            ->join('d.store', 's')
            ->where('d.host = :host')
            ->setParameter('host', strtolower($host))
            ->getQuery()
            ->getOneOrNullResult();
    }
}
