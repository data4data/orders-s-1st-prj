<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Ordering;

use App\Domain\Ordering\OrderState;
use App\Domain\Payment\PaymentState;
use PHPUnit\Framework\TestCase;

final class OrderStateTest extends TestCase
{
    public function testEveryPlaceHasALabelAndABadge(): void
    {
        $badges = [];
        foreach (OrderState::cases() as $state) {
            self::assertSame('order.state.'.$state->value, $state->label());
            $badges[] = $state->badge();
        }
        self::assertSame(['secondary', 'warn', 'success', 'info', 'indigo', 'teal', 'danger', 'purple'], $badges);
    }

    public function testStockIsHeldFromCheckoutUntilCancelOrRefund(): void
    {
        $holding = array_values(array_filter(OrderState::cases(), static fn (OrderState $s) => $s->holdsStock()));

        self::assertSame([OrderState::PaymentPending, OrderState::Paid, OrderState::Processing, OrderState::Shipped, OrderState::Delivered], $holding);
    }

    public function testOnlyPendingAndAuthorizedPaymentsAreOpen(): void
    {
        $open = array_values(array_filter(PaymentState::cases(), static fn (PaymentState $s) => $s->isOpen()));

        self::assertSame([PaymentState::Pending, PaymentState::Authorized], $open);
    }
}
