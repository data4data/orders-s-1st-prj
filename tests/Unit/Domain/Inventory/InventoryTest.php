<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Inventory;

use App\Domain\Inventory\InsufficientStockException;
use App\Domain\Inventory\StockLevel;
use App\Domain\Inventory\StockPolicy;
use App\Domain\Inventory\StockRequest;
use App\Domain\Shared\Quantity;
use PHPUnit\Framework\TestCase;

final class InventoryTest extends TestCase
{
    public function testTheOrderLifecycleMovesStockCorrectly(): void
    {
        $stock = new StockLevel(onHand: 10);

        $reserved = $stock->reserve(Quantity::of(3));          // checkout
        self::assertSame([10, 3, 7], [$reserved->onHand, $reserved->reserved, $reserved->available()]);

        $paid = $reserved->commit(Quantity::of(3));             // pay
        self::assertSame([7, 0, 7], [$paid->onHand, $paid->reserved, $paid->available()]);

        $released = $reserved->release(Quantity::of(3));        // cancel before pay
        self::assertSame([10, 0], [$released->onHand, $released->reserved]);

        $restocked = $paid->restock(Quantity::of(3));           // cancel after pay
        self::assertSame(10, $restocked->onHand);
    }

    public function testCannotReserveMoreThanAvailable(): void
    {
        $stock = new StockLevel(onHand: 5, reserved: 3);
        self::assertTrue($stock->canReserve(Quantity::of(2)));
        self::assertFalse($stock->canReserve(Quantity::of(3)));

        $this->expectException(InsufficientStockException::class);
        $this->expectExceptionMessage('Cannot reserve 3: only 2 available.');
        $stock->reserve(Quantity::of(3));
    }

    public function testCannotCommitOrReleaseWhatIsNotReserved(): void
    {
        $this->expectException(InsufficientStockException::class);
        (new StockLevel(onHand: 5, reserved: 1))->commit(Quantity::of(2));
    }

    public function testCannotReleaseWhatIsNotReserved(): void
    {
        $this->expectException(InsufficientStockException::class);
        (new StockLevel(onHand: 5))->release(Quantity::of(1));
    }

    public function testInvariantsAreChecked(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new StockLevel(onHand: 1, reserved: 2);
    }

    public function testNegativeStockIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new StockLevel(onHand: -1);
    }

    public function testLowStockUsesTheStoreThreshold(): void
    {
        self::assertTrue((new StockLevel(onHand: 12, reserved: 2))->isLow(10));
        self::assertFalse((new StockLevel(onHand: 12, reserved: 1))->isLow(10));
    }

    public function testTheCartIsCheckedAsAWhole(): void
    {
        $policy = new StockPolicy();
        $drum = new StockLevel(onHand: 3);

        $ok = [new StockRequest('SP530-208', $drum, Quantity::of(2)), new StockRequest('G12-5', new StockLevel(10), Quantity::of(1))];
        self::assertTrue($policy->canReserveAll($ok));

        // The same pack size twice in the cart: 2 + 2 > 3
        $shortages = $policy->shortages([new StockRequest('SP530-208', $drum, Quantity::of(2)), new StockRequest('SP530-208', $drum, Quantity::of(2))]);
        self::assertCount(1, $shortages);
        self::assertSame(['SP530-208', 4, 3], [$shortages[0]->sku, $shortages[0]->requested, $shortages[0]->available]);
        self::assertFalse($policy->canReserveAll([new StockRequest('X', new StockLevel(0), Quantity::of(1))]));
    }
}
