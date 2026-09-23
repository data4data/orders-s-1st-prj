<?php

declare(strict_types=1);

namespace App\Application\Ordering\Admin;

use App\Application\Exception\NotFoundException;
use App\Application\Validation\ValidationException;
use App\Domain\Discount\CouponType;
use App\Domain\Money\Money;
use App\Entity\Coupon;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Admin → Coupons. Codes are unique per store; coupons are deactivated rather than deleted,
 * because placed orders refer to them.
 */
final readonly class CouponAdminHandler
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[AsMessageHandler(bus: 'query.bus')]
    public function list(ListCoupons $query): array
    {
        return array_map(static fn (Coupon $c) => [
            'id' => $c->getId(),
            'code' => $c->getCode(),
            'type' => $c->getType()->value,
            'percent' => $c->getPercent(),
            'amount' => null !== $c->getAmount() ? Money::of($c->getAmount(), 'EUR')->toDecimalString() : null,
            'minOrderNet' => null !== $c->getMinOrderNet() ? Money::of($c->getMinOrderNet(), 'EUR')->toDecimalString() : null,
            'validFrom' => $c->getValidFrom()?->format('Y-m-d'),
            'validTo' => $c->getValidTo()?->format('Y-m-d'),
            'usageLimit' => $c->getUsageLimit(),
            'timesUsed' => $c->getTimesUsed(),
            'isActive' => $c->isActive(),
        ], $this->entityManager->getRepository(Coupon::class)->findBy([], ['code' => 'ASC']));
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function save(SaveCoupon $command): int
    {
        $input = $command->input;
        $repository = $this->entityManager->getRepository(Coupon::class);
        $existing = $repository->findOneBy(['code' => Coupon::normalizeCode($input->code)]);
        if (null !== $existing && $existing->getId() !== $command->id) {
            throw ValidationException::forField('code', 'Another coupon already uses this code.');
        }

        $type = CouponType::from($input->type);
        $amount = null !== $input->amount && '' !== $input->amount ? Money::fromDecimal($input->amount, 'EUR')->amount : null;
        if (null === $command->id) {
            $coupon = new Coupon($input->code, $type, $input->percent, $amount);
            $this->entityManager->persist($coupon);
        } else {
            $coupon = $repository->find($command->id) ?? throw NotFoundException::of('Coupon', (string) $command->id);
        }
        $coupon->update($input->code, $type, CouponType::Percentage === $type ? number_format((float) $input->percent, 2, '.', '') : null, $amount, $input->isActive);
        $coupon->limit(
            null !== $input->minOrderNet && '' !== $input->minOrderNet ? Money::fromDecimal($input->minOrderNet, 'EUR')->amount : null,
            null !== $input->validFrom && '' !== $input->validFrom ? new \DateTimeImmutable($input->validFrom.' 00:00:00') : null,
            null !== $input->validTo && '' !== $input->validTo ? new \DateTimeImmutable($input->validTo.' 23:59:59') : null,
            $input->usageLimit,
        );
        $this->entityManager->flush();

        return (int) $coupon->getId();
    }
}
