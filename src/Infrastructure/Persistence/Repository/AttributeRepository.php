<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Application\Catalog\Port\AttributeRepositoryInterface;
use App\Entity\Attribute;
use App\Entity\ProductAttributeValue;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Attribute>
 */
final class AttributeRepository extends ServiceEntityRepository implements AttributeRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attribute::class);
    }

    public function findById(int $id): ?Attribute
    {
        return $this->find($id);
    }

    public function findAllOrdered(): array
    {
        /** @var list<Attribute> $attributes */
        $attributes = $this->createQueryBuilder('a')->addSelect('o')->leftJoin('a.options', 'o')
            ->orderBy('a.position', 'ASC')->addOrderBy('a.name', 'ASC')->addOrderBy('o.position', 'ASC')
            ->getQuery()->getResult();

        return $attributes;
    }

    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        $qb = $this->createQueryBuilder('a')->select('COUNT(a.id)')->where('a.code = :code')->setParameter('code', $code);
        if (null !== $exceptId) {
            $qb->andWhere('a.id <> :id')->setParameter('id', $exceptId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function countProductsUsing(Attribute $attribute): int
    {
        return (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(DISTINCT IDENTITY(v.product))')->from(ProductAttributeValue::class, 'v')
            ->where('v.attribute = :attribute')->setParameter('attribute', $attribute)
            ->getQuery()->getSingleScalarResult();
    }

    public function save(Attribute $attribute): void
    {
        $this->getEntityManager()->persist($attribute);
        $this->getEntityManager()->flush();
    }

    public function remove(Attribute $attribute): void
    {
        $this->getEntityManager()->remove($attribute);
        $this->getEntityManager()->flush();
    }
}
