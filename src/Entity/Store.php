<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Platform table: one row per shop. Tenant tables point here through `store_id`.
 */
#[ORM\Entity]
#[ORM\Table(name: 'store')]
class Store
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $publicId;

    #[ORM\Column(length: 3, options: ['fixed' => true])]
    private string $currencyCode;

    #[ORM\Column(length: 10)]
    private string $defaultLocale = 'en';

    #[ORM\Column(length: 32)]
    private string $paymentGatewayCode = 'fake';

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $contactEmail = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $logoUrl = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $faviconUrl = null;

    #[ORM\Column(length: 7, options: ['fixed' => true])]
    private string $primaryColor = '#0F2742';

    #[ORM\Column(length: 7, options: ['fixed' => true])]
    private string $accentColor = '#F2A900';

    #[ORM\Column(options: ['unsigned' => true])]
    private int $lowStockThreshold = 10;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\Column(length: 64, unique: true)]
        private string $code,
        #[ORM\Column(length: 120)]
        private string $name,
        #[ORM\ManyToOne(targetEntity: Country::class)]
        #[ORM\JoinColumn(name: 'country_code', referencedColumnName: 'code', nullable: false)]
        private Country $country,
        string $currencyCode,
        #[ORM\Column(length: 16)]
        private string $orderNumberPrefix,
    ) {
        $this->publicId = Uuid::v7();
        $this->currencyCode = strtoupper($currencyCode);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicId(): Uuid
    {
        return $this->publicId;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCountry(): Country
    {
        return $this->country;
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    public function getOrderNumberPrefix(): string
    {
        return $this->orderNumberPrefix;
    }

    public function getPaymentGatewayCode(): string
    {
        return $this->paymentGatewayCode;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function getLogoUrl(): ?string
    {
        return $this->logoUrl;
    }

    public function getFaviconUrl(): ?string
    {
        return $this->faviconUrl;
    }

    public function getPrimaryColor(): string
    {
        return $this->primaryColor;
    }

    public function getAccentColor(): string
    {
        return $this->accentColor;
    }

    public function getLowStockThreshold(): int
    {
        return $this->lowStockThreshold;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function changeBranding(?string $logoUrl, ?string $faviconUrl, string $primaryColor, string $accentColor): void
    {
        $this->logoUrl = $logoUrl;
        $this->faviconUrl = $faviconUrl;
        $this->primaryColor = strtoupper($primaryColor);
        $this->accentColor = strtoupper($accentColor);
    }

    public function changeContact(?string $contactEmail, string $defaultLocale): void
    {
        $this->contactEmail = $contactEmail;
        $this->defaultLocale = $defaultLocale;
    }

    public function changeLowStockThreshold(int $threshold): void
    {
        if ($threshold < 0) {
            throw new \InvalidArgumentException('The low-stock threshold cannot be negative.');
        }
        $this->lowStockThreshold = $threshold;
    }

    public function activate(): void
    {
        $this->isActive = true;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }
}
