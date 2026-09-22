<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Application\Catalog\Port\TaxCategoryRepositoryInterface;
use App\Entity\TaxCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaxCategory>
 */
final class TaxCategoryRepository extends ServiceEntityRepository implements TaxCategoryRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaxCategory::class);
    }

    public function findById(int $id): ?TaxCategory
    {
        return $this->find($id);
    }

    public function findByCode(string $code): ?TaxCategory
    {
        return $this->findOneBy(['code' => $code]);
    }

    public function findAll(): array
    {
        return $this->findBy([], ['id' => 'ASC']);
    }
}
