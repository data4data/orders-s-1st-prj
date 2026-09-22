<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Discount\Coupon as DomainCoupon;
use App\Domain\Discount\CouponType;
use App\Domain\Money\Money;
use App\Domain\Shared\Percentage;
use App\Entity\Contract\TenantAwareInterface;
use App\Entity\Contract\TenantAwareTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tenant table: a coupon code of one store (percentage DECIMAL(5,2) or fixed amount in cents).
 * The rules live in Domain\Discount; this entity only stores them.
 */
#[ORM\Entity]
#[ORM\Table(name: 'coupon')]
#[ORM\UniqueConstraint(name: 'uniq_coupon_store_code', columns: ['store_id', 'code'])]
class Coupon implements TenantAwareInterface
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(length: 40)]
    private string $code;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $percent = null;

    #[ORM\Column(nullable: true, options: ['unsigned' => true])]
    private ?int $amount = null;

    #[ORM\Column(nullable: true, options: ['unsigned' => true])]
    private ?int $minOrderNet = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $validFrom = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $validTo = null;

    #[ORM\Column(nullable: true, options: ['unsigned' => true])]
    private ?int $usageLimit = null;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $timesUsed = 0;

    #[ORM\Column]
    private bool $isActive = true;

    public function __construct(
        string $code,
        #[ORM\Column(length: 16, enumType: CouponType::class)]
        private CouponType $type,
        ?string $percent = null,
        ?int $amount = null,
    ) {
        $this->code = self::normalizeCode($code);
        $this->percent = $percent;
        $this->amount = $amount;
    }

    public static function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }

    public function limit(?int $minOrderNet, ?\DateTimeImmutable $validFrom, ?\DateTimeImmutable $validTo, ?int $usageLimit): void
    {
        $this->minOrderNet = $minOrderNet;
        $this->validFrom = $validFrom;
        $this->validTo = $validTo;
        $this->usageLimit = $usageLimit;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getType(): CouponType
    {
        return $this->type;
    }

    public function getTimesUsed(): int
    {
        return $this->timesUsed;
    }

    public function update(string $code, CouponType $type, ?string $percent, ?int $amount, bool $isActive): void
    {
        $this->code = self::normalizeCode($code);
        $this->type = $type;
        $this->percent = CouponType::Percentage === $type ? $percent : null;
        $this->amount = CouponType::Fixed === $type ? $amount : null;
        $this->isActive = $isActive;
    }

    public function getPercent(): ?string
    {
        return $this->percent;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function getMinOrderNet(): ?int
    {
        return $this->minOrderNet;
    }

    public function getValidFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function getValidTo(): ?\DateTimeImmutable
    {
        return $this->validTo;
    }

    public function getUsageLimit(): ?int
    {
        return $this->usageLimit;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function recordUse(): void
    {
        ++$this->timesUsed;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }

    public function toDomain(string $currency): DomainCoupon
    {
        return new DomainCoupon(
            $this->code,
            $this->type,
            null !== $this->percent ? Percentage::of($this->percent) : null,
            null !== $this->amount ? Money::of($this->amount, $currency) : null,
            null !== $this->minOrderNet ? Money::of($this->minOrderNet, $currency) : null,
            $this->validFrom,
            $this->validTo,
            $this->usageLimit,
            $this->timesUsed,
            $this->isActive,
        );
    }
}
