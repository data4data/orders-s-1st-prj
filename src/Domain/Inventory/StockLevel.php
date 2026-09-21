<?php

declare(strict_types=1);

namespace App\Domain\Inventory;

use App\Domain\Shared\Quantity;

/**
 * Stock of one pack size: physically on hand, and how much of it payment_pending orders hold.
 *
 *   checkout → reserve()   pay → commit()   cancel before pay → release()   cancel after pay → restock()
 */
final readonly class StockLevel
{
    public function __construct(
        public int $onHand,
        public int $reserved = 0,
    ) {
        if ($onHand < 0 || $reserved < 0) {
            throw new \InvalidArgumentException('Stock numbers cannot be negative.');
        }
        if ($reserved > $onHand) {
            throw new \InvalidArgumentException(sprintf('Reserved (%d) cannot exceed on hand (%d).', $reserved, $onHand));
        }
    }

    /** What can still be sold. */
    public function available(): int
    {
        return $this->onHand - $this->reserved;
    }

    public function canReserve(Quantity $quantity): bool
    {
        return $quantity->value <= $this->available();
    }

    public function reserve(Quantity $quantity): self
    {
        if (!$this->canReserve($quantity)) {
            throw InsufficientStockException::toReserve($quantity->value, $this->available());
        }

        return new self($this->onHand, $this->reserved + $quantity->value);
    }

    /** Payment received: the reserved units leave the warehouse. */
    public function commit(Quantity $quantity): self
    {
        $this->assertReserved($quantity);

        return new self($this->onHand - $quantity->value, $this->reserved - $quantity->value);
    }

    /** Unpaid order cancelled: the reservation is freed. */
    public function release(Quantity $quantity): self
    {
        $this->assertReserved($quantity);

        return new self($this->onHand, $this->reserved - $quantity->value);
    }

    /** Paid order cancelled before shipping, or new delivery: units come back. */
    public function restock(Quantity $quantity): self
    {
        return new self($this->onHand + $quantity->value, $this->reserved);
    }

    /** Low stock: available at or below the store's threshold. */
    public function isLow(int $threshold): bool
    {
        return $this->available() <= $threshold;
    }

    private function assertReserved(Quantity $quantity): void
    {
        if ($quantity->value > $this->reserved) {
            throw InsufficientStockException::notReserved($quantity->value, $this->reserved);
        }
    }
}
