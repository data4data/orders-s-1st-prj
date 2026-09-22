<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Application\Catalog\Port\CategoryRepositoryInterface;
use App\Entity\Category;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
final class CategoryRepository extends ServiceEntityRepository implements CategoryRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function findById(int $id): ?Category
    {
        return $this->find($id);
    }

    public function findBySlug(string $slug): ?Category
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    public function findTopLevel(bool $activeOnly): array
    {
        $criteria = ['parent' => null];
        if ($activeOnly) {
            $criteria['isActive'] = true;
        }

        return $this->findBy($criteria, ['position' => 'ASC', 'name' => 'ASC']);
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $qb = $this->createQueryBuilder('c')->select('COUNT(c.id)')->where('c.slug = :slug')->setParameter('slug', $slug);
        if (null !== $exceptId) {
            $qb->andWhere('c.id <> :id')->setParameter('id', $exceptId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function countProducts(Category $category): int
    {
        return (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(DISTINCT p.id)')->from(Product::class, 'p')->join('p.categories', 'c')
            ->where('c = :category')->setParameter('category', $category)
            ->getQuery()->getSingleScalarResult();
    }

    public function save(Category $category): void
    {
        $this->getEntityManager()->persist($category);
        $this->getEntityManager()->flush();
    }

    public function remove(Category $category): void
    {
        $this->getEntityManager()->remove($category);
        $this->getEntityManager()->flush();
    }
}
