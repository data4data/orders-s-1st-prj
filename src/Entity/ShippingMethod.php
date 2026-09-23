<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Shipping\ShippingMethodSpec;
use App\Entity\Contract\TenantAwareInterface;
use App\Entity\Contract\TenantAwareTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tenant table: a shipping option of one store. `calculator` selects a ShippingCalculatorInterface
 * (flat, weight_based, free_over_threshold); `config` holds its parameters in cents and grams.
 */
#[ORM\Entity]
#[ORM\Table(name: 'shipping_method')]
#[ORM\UniqueConstraint(name: 'uniq_shipping_method_store_code', columns: ['store_id', 'code'])]
class ShippingMethod implements TenantAwareInterface
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column]
    private bool $isActive = true;

    /**
     * @param array<string, mixed> $config
     * @param list<string>|null    $allowedCountries null = every country
     */
    public function __construct(
        #[ORM\Column(length: 40)]
        private string $code,
        #[ORM\Column(length: 100)]
        private string $name,
        #[ORM\Column(length: 40)]
        private string $calculator,
        #[ORM\Column(type: 'json')]
        private array $config,
        #[ORM\ManyToOne(targetEntity: TaxCategory::class)]
        #[ORM\JoinColumn(nullable: false)]
        private TaxCategory $taxCategory,
        #[ORM\Column(type: 'json', nullable: true)]
        private ?array $allowedCountries = null,
        #[ORM\Column]
        private int $position = 0,
        #[ORM\Column(length: 200, nullable: true)]
        private ?string $description = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getTaxCategory(): TaxCategory
    {
        return $this->taxCategory;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string>|null    $allowedCountries
     */
    public function update(string $code, string $name, ?string $description, string $calculator, array $config, ?array $allowedCountries, int $position, bool $isActive): void
    {
        $this->code = $code;
        $this->name = $name;
        $this->description = $description;
        $this->calculator = $calculator;
        $this->config = $config;
        $this->allowedCountries = $allowedCountries;
        $this->position = $position;
        $this->isActive = $isActive;
    }

    public function getCalculator(): string
    {
        return $this->calculator;
    }

    /** @return array<string, mixed> */
    public function getConfig(): array
    {
        return $this->config;
    }

    /** @return list<string>|null */
    public function getAllowedCountries(): ?array
    {
        return $this->allowedCountries;
    }

    public function toSpec(): ShippingMethodSpec
    {
        return new ShippingMethodSpec($this->code, $this->calculator, $this->config, $this->allowedCountries);
    }
}
