<?php

declare(strict_types=1);

namespace App\Infrastructure\Catalog;

use App\Application\Catalog\Port\ProductSearchInterface;
use App\Application\Catalog\ProductSearchCriteria;
use App\Entity\Product;
use App\Entity\ProductAttributeValue;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Catalog search with Doctrine (so the tenant filter scopes every query to the store).
 * Only active products with at least one active pack size are listed. Gross prices are computed
 * in SQL as net × (1 + VAT) per tax category, so price filters and sorting use what customers see.
 */
final readonly class DoctrineProductSearch implements ProductSearchInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function search(ProductSearchCriteria $criteria): array
    {
        $total = (int) $this->filtered($criteria)->select('COUNT(DISTINCT p.id)')->getQuery()->getSingleScalarResult();

        $qb = $this->filtered($criteria)->select('p.id AS id')->groupBy('p.id');
        $gross = $this->grossExpression($criteria);
        match ($criteria->sort) {
            'price_asc' => $qb->addSelect("MIN($gross) AS HIDDEN sortPrice")->orderBy('sortPrice', 'ASC'),
            'price_desc' => $qb->addSelect("MIN($gross) AS HIDDEN sortPrice")->orderBy('sortPrice', 'DESC'),
            'name' => $qb->addSelect('MIN(p.name) AS HIDDEN sortName')->orderBy('sortName', 'ASC'),
            default => '' !== $criteria->search
                ? $qb->addSelect('MIN(CASE WHEN p.name LIKE :prefix THEN 0 ELSE 1 END) AS HIDDEN relevance')->setParameter('prefix', addcslashes($criteria->search, '%_').'%')->orderBy('relevance', 'ASC')->addOrderBy('p.id', 'DESC')
                : $qb->orderBy('p.id', 'DESC'),
        };
        $qb->addOrderBy('p.id', 'ASC')->setFirstResult(($criteria->page - 1) * $criteria->perPage)->setMaxResults($criteria->perPage);

        return ['ids' => array_map('intval', array_column($qb->getQuery()->getScalarResult(), 'id')), 'total' => $total];
    }

    public function optionCounts(ProductSearchCriteria $criteria, int $attributeId): array
    {
        $rows = $this->filtered($criteria)
            ->select('IDENTITY(fv.option) AS optionId, COUNT(DISTINCT p.id) AS products')
            ->join(ProductAttributeValue::class, 'fv', 'WITH', 'fv.product = p AND IDENTITY(fv.attribute) = :facetAttribute')
            ->setParameter('facetAttribute', $attributeId)
            ->groupBy('fv.option')
            ->getQuery()->getScalarResult();

        return array_combine(array_map('intval', array_column($rows, 'optionId')), array_map('intval', array_column($rows, 'products')));
    }

    public function volumeCounts(ProductSearchCriteria $criteria): array
    {
        $rows = $this->filtered($criteria)
            ->select('v.volumeMl AS volume, COUNT(DISTINCT p.id) AS products')
            ->groupBy('v.volumeMl')->orderBy('v.volumeMl', 'ASC')
            ->getQuery()->getScalarResult();

        return array_combine(array_map('intval', array_column($rows, 'volume')), array_map('intval', array_column($rows, 'products')));
    }

    /**
     * Products (joined with their matching active variants as `v`) that pass every filter.
     */
    private function filtered(ProductSearchCriteria $criteria): QueryBuilder
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->from(Product::class, 'p')
            ->join('p.variants', 'v', 'WITH', 'v.isActive = true')
            ->where('p.isActive = true');

        if ([] !== $criteria->categoryIds) {
            $qb->andWhere('EXISTS (SELECT 1 FROM '.Product::class.' pc JOIN pc.categories cat WHERE pc = p AND cat.id IN (:categoryIds))')
                ->setParameter('categoryIds', $criteria->categoryIds);
        }
        if ('' !== $criteria->search) {
            $qb->andWhere('p.name LIKE :search OR p.description LIKE :search OR v.sku LIKE :search')
                ->setParameter('search', '%'.addcslashes($criteria->search, '%_').'%');
        }
        $i = 0;
        foreach ($criteria->optionIds as $optionIds) {
            ++$i;
            $qb->andWhere('EXISTS (SELECT 1 FROM '.ProductAttributeValue::class." pav$i WHERE pav$i.product = p AND IDENTITY(pav$i.option) IN (:options$i))")
                ->setParameter("options$i", $optionIds);
        }
        if ([] !== $criteria->volumesMl) {
            $qb->andWhere('v.volumeMl IN (:volumes)')->setParameter('volumes', $criteria->volumesMl);
        }
        if ($criteria->inStockOnly) {
            $qb->andWhere('v.onHand > v.reserved');
        }
        if (null !== $criteria->minGross) {
            $qb->andWhere($this->grossExpression($criteria).' >= :minGross')->setParameter('minGross', $criteria->minGross);
        }
        if (null !== $criteria->maxGross) {
            $qb->andWhere($this->grossExpression($criteria).' <= :maxGross')->setParameter('maxGross', $criteria->maxGross);
        }

        return $qb;
    }

    /** DQL for the gross price of variant `v`: net × (1 + VAT of the product's tax category). */
    private function grossExpression(ProductSearchCriteria $criteria): string
    {
        if ([] === $criteria->grossFactor) {
            return 'v.priceNet';
        }
        $cases = '';
        foreach ($criteria->grossFactor as $taxCategoryId => $factor) {
            $cases .= sprintf(' WHEN IDENTITY(p.taxCategory) = %d THEN %s', $taxCategoryId, preg_replace('/[^0-9.]/', '', $factor));
        }

        return "(v.priceNet * (CASE$cases ELSE 1 END))";
    }
}
