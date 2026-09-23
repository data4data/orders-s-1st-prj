<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Shared\Percentage;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Platform table: the VAT rate for a country and tax category over a period (decision #10).
 */
#[ORM\Entity]
#[ORM\Table(name: 'tax_rate')]
#[ORM\UniqueConstraint(name: 'uniq_tax_rate_period', columns: ['country_code', 'tax_category_id', 'valid_from'])]
class TaxRate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $rate;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Country::class)]
        #[ORM\JoinColumn(name: 'country_code', referencedColumnName: 'code', nullable: false)]
        private Country $country,
        #[ORM\ManyToOne(targetEntity: TaxCategory::class)]
        #[ORM\JoinColumn(name: 'tax_category_id', nullable: false)]
        private TaxCategory $taxCategory,
        string $rate,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $validFrom,
        #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $validTo = null,
    ) {
        $this->rate = Percentage::of($rate)->toString();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCountry(): Country
    {
        return $this->country;
    }

    public function getTaxCategory(): TaxCategory
    {
        return $this->taxCategory;
    }

    /** "21.00" */
    public function getRate(): string
    {
        return $this->rate;
    }

    public function getValidFrom(): \DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function getValidTo(): ?\DateTimeImmutable
    {
        return $this->validTo;
    }
}
