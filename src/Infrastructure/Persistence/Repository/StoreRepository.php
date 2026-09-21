<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Application\Tenancy\Port\StoreRepositoryInterface;
use App\Entity\Store;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Store>
 */
final class StoreRepository extends ServiceEntityRepository implements StoreRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Store::class);
    }

    public function findById(int $id): ?Store
    {
        return $this->find($id);
    }

    public function findByCode(string $code): ?Store
    {
        return $this->findOneBy(['code' => $code]);
    }

    public function findByPublicId(Uuid $publicId): ?Store
    {
        return $this->findOneBy(['publicId' => $publicId]);
    }

    public function findAllActive(): array
    {
        return $this->findBy(['isActive' => true], ['name' => 'ASC']);
    }
}
