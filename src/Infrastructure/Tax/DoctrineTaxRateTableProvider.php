<?php

declare(strict_types=1);

namespace App\Infrastructure\Tax;

use App\Domain\Tax\TaxRate as DomainTaxRate;
use App\Domain\Tax\TaxRatePeriod;
use App\Domain\Tax\TaxRateTable;
use App\Domain\Tax\TaxRateTableProviderInterface;
use App\Entity\TaxRate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Builds the pure-PHP VAT table from the platform tax_rate table (once per request).
 */
final class DoctrineTaxRateTableProvider implements TaxRateTableProviderInterface, ResetInterface
{
    private ?TaxRateTable $table = null;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function table(): TaxRateTable
    {
        return $this->table ??= new TaxRateTable(array_map(
            static fn (TaxRate $rate): TaxRatePeriod => new TaxRatePeriod(
                $rate->getCountry()->getCode(),
                $rate->getTaxCategory()->getCode(),
                DomainTaxRate::of($rate->getRate()),
                $rate->getValidFrom(),
                $rate->getValidTo(),
            ),
            $this->entityManager->getRepository(TaxRate::class)->findAll(),
        ));
    }

    public function reset(): void
    {
        $this->table = null;
    }
}
