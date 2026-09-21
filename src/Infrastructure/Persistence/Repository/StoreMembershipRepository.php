<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Application\Tenancy\Port\StoreMembershipRepositoryInterface;
use App\Entity\StaffUser;
use App\Entity\Store;
use App\Entity\StoreMembership;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StoreMembership>
 */
final class StoreMembershipRepository extends ServiceEntityRepository implements StoreMembershipRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StoreMembership::class);
    }

    public function findOne(StaffUser $staffUser, Store $store): ?StoreMembership
    {
        return $this->findOneBy(['staffUser' => $staffUser, 'store' => $store]);
    }

    public function findForStaffUser(StaffUser $staffUser): array
    {
        /** @var list<StoreMembership> $memberships */
        $memberships = $this->createQueryBuilder('m')
            ->addSelect('s')
            ->join('m.store', 's')
            ->where('m.staffUser = :staffUser')
            ->andWhere('s.isActive = true')
            ->setParameter('staffUser', $staffUser)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $memberships;
    }
}
