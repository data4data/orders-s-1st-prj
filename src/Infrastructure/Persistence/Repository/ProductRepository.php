<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Application\Catalog\Port\ProductRepositoryInterface;
use App\Entity\Product;
use App\Entity\ProductVariant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Product>
 */
final class ProductRepository extends ServiceEntityRepository implements ProductRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findByPublicId(Uuid $publicId): ?Product
    {
        return $this->findOneBy(['publicId' => $publicId]);
    }

    public function findActiveBySlug(string $slug): ?Product
    {
        return $this->findOneBy(['slug' => $slug, 'isActive' => true]);
    }

    public function slugExists(string $slug, ?int $exceptProductId = null): bool
    {
        $qb = $this->createQueryBuilder('p')->select('COUNT(p.id)')->where('p.slug = :slug')->setParameter('slug', $slug);
        if (null !== $exceptProductId) {
            $qb->andWhere('p.id <> :id')->setParameter('id', $exceptProductId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function skusUsedElsewhere(array $skus, ?int $exceptProductId = null): array
    {
        if ([] === $skus) {
            return [];
        }
        $qb = $this->getEntityManager()->createQueryBuilder()->select('v.sku')->from(ProductVariant::class, 'v')
            ->where('v.sku IN (:skus)')->setParameter('skus', $skus);
        if (null !== $exceptProductId) {
            $qb->andWhere('IDENTITY(v.product) <> :id')->setParameter('id', $exceptProductId);
        }

        return array_column($qb->getQuery()->getArrayResult(), 'sku');
    }

    public function findForListing(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }
        /** @var list<Product> $products */
        $products = $this->createQueryBuilder('p')
            ->addSelect('v', 'i', 'av', 'a', 'o')
            ->leftJoin('p.variants', 'v')->leftJoin('p.images', 'i')
            ->leftJoin('p.attributeValues', 'av')->leftJoin('av.attribute', 'a')->leftJoin('av.option', 'o')
            ->where('p.id IN (:ids)')->setParameter('ids', $ids)
            ->getQuery()->getResult();

        $byId = [];
        foreach ($products as $product) {
            $byId[(int) $product->getId()] = $product;
        }

        return array_values(array_filter(array_map(static fn (int $id) => $byId[$id] ?? null, $ids)));
    }

    public function adminPage(string $search, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('p');
        if ('' !== $search) {
            $qb->leftJoin('p.variants', 'sv')->andWhere('p.name LIKE :q OR sv.sku LIKE :q')->setParameter('q', '%'.addcslashes($search, '%_').'%');
        }
        $total = (int) (clone $qb)->select('COUNT(DISTINCT p.id)')->getQuery()->getSingleScalarResult();

        $ids = array_column((clone $qb)->select('DISTINCT p.id, p.name')->orderBy('p.name', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getArrayResult(), 'id');

        return ['items' => $this->findForListing(array_map('intval', $ids)), 'total' => $total];
    }

    public function findRelated(Product $product, int $limit): array
    {
        if ($product->getCategories()->isEmpty()) {
            return [];
        }
        $ids = array_column($this->createQueryBuilder('p')->select('DISTINCT p.id')->join('p.categories', 'c')
            ->where('c IN (:categories)')->andWhere('p <> :product')->andWhere('p.isActive = true')
            ->setParameter('categories', $product->getCategories()->toArray())->setParameter('product', $product)
            ->setMaxResults($limit)->getQuery()->getArrayResult(), 'id');

        return $this->findForListing(array_map('intval', $ids));
    }

    public function save(Product $product): void
    {
        $this->getEntityManager()->persist($product);
        $this->getEntityManager()->flush();
    }

    public function remove(Product $product): void
    {
        $this->getEntityManager()->remove($product);
        $this->getEntityManager()->flush();
    }
}
