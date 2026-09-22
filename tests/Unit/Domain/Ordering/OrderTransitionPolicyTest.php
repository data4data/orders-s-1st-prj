<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Ordering;

use App\Domain\Ordering\ActorType;
use App\Domain\Ordering\OrderFacts;
use App\Domain\Ordering\OrderState;
use App\Domain\Ordering\OrderTransitionPolicy;
use App\Domain\Ordering\TransitionBlock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OrderTransitionPolicyTest extends TestCase
{
    private OrderTransitionPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new OrderTransitionPolicy();
    }

    public function testCheckoutNeedsLinesAddressesAndShipping(): void
    {
        self::assertTrue($this->policy->allows('checkout', $this->facts(OrderState::Draft, ActorType::Customer)));
        self::assertSame(
            [TransitionBlock::NoLines, TransitionBlock::AddressMissing, TransitionBlock::ShippingMethodMissing],
            $this->policy->blocks('checkout', $this->facts(OrderState::Draft, ActorType::Customer, lines: 0, billing: false, shipping: false, method: false)),
        );
        self::assertSame([TransitionBlock::AddressMissing], $this->policy->blocks('checkout', $this->facts(OrderState::Draft, ActorType::Customer, shipping: false)));
    }

    public function testPayNeedsTheFullAmountCaptured(): void
    {
        self::assertSame([TransitionBlock::NotPaid], $this->policy->blocks('pay', $this->facts(OrderState::PaymentPending, ActorType::System, captured: 9688)));
        self::assertTrue($this->policy->allows('pay', $this->facts(OrderState::PaymentPending, ActorType::System, captured: 9689)));
    }

    public function testFulfilmentIsForStaffAndShippingNeedsThePayment(): void
    {
        self::assertSame([TransitionBlock::StaffOnly], $this->policy->blocks('start_processing', $this->facts(OrderState::Paid, ActorType::System, captured: 9689)));
        self::assertTrue($this->policy->allows('start_processing', $this->facts(OrderState::Paid, ActorType::Staff, captured: 9689)));
        self::assertSame([TransitionBlock::StaffOnly, TransitionBlock::NotPaid], $this->policy->blocks('ship', $this->facts(OrderState::Processing, ActorType::Customer)));
        self::assertTrue($this->policy->allows('ship', $this->facts(OrderState::Processing, ActorType::Staff, captured: 9689)));
        self::assertTrue($this->policy->allows('deliver', $this->facts(OrderState::Shipped, ActorType::System, captured: 9689)));
        self::assertSame([TransitionBlock::StaffOnly], $this->policy->blocks('deliver', $this->facts(OrderState::Shipped, ActorType::Customer, captured: 9689)));
    }

    /**
     * @return iterable<string, array{OrderState, ActorType}>
     */
    public static function unpaidCancels(): iterable
    {
        yield 'customer, awaiting payment' => [OrderState::PaymentPending, ActorType::Customer];
        yield 'expiry job' => [OrderState::PaymentPending, ActorType::System];
        yield 'staff, cart' => [OrderState::Draft, ActorType::Staff];
    }

    #[DataProvider('unpaidCancels')]
    public function testAnyoneMayCancelBeforePayment(OrderState $state, ActorType $actor): void
    {
        self::assertTrue($this->policy->allows('cancel', $this->facts($state, $actor)));
    }

    public function testAPaidOrderIsCancelledByStaffAfterTheFullRefund(): void
    {
        self::assertSame([TransitionBlock::StaffOnly], $this->policy->blocks('cancel', $this->facts(OrderState::Paid, ActorType::Customer, captured: 9689)));
        self::assertSame([TransitionBlock::NotRefunded], $this->policy->blocks('cancel', $this->facts(OrderState::Processing, ActorType::Staff, captured: 9689, refunded: 5000)));
        self::assertTrue($this->policy->allows('cancel', $this->facts(OrderState::Paid, ActorType::Staff, captured: 9689, refunded: 9689)));
    }

    public function testRefundAfterShippingNeedsTheMoneyBack(): void
    {
        self::assertSame([TransitionBlock::NotRefunded], $this->policy->blocks('refund', $this->facts(OrderState::Delivered, ActorType::Staff, captured: 9689)));
        self::assertTrue($this->policy->allows('refund', $this->facts(OrderState::Shipped, ActorType::Staff, captured: 9689, refunded: 9689)));
        self::assertSame([TransitionBlock::NotRefunded], $this->policy->blocks('refund', $this->facts(OrderState::Delivered, ActorType::Staff)));
        self::assertSame([TransitionBlock::StaffOnly], $this->policy->blocks('refund', $this->facts(OrderState::Delivered, ActorType::System, captured: 9689, refunded: 9689)));
        self::assertSame([], $this->policy->blocks('unknown', $this->facts(OrderState::Delivered, ActorType::Staff)));
    }

    public function testEveryBlockHasAMessage(): void
    {
        foreach (TransitionBlock::cases() as $block) {
            self::assertStringEndsWith('.', $block->message());
        }
    }

    private function facts(OrderState $state, ActorType $actor, int $lines = 2, bool $billing = true, bool $shipping = true, bool $method = true, int $captured = 0, int $refunded = 0): OrderFacts
    {
        return new OrderFacts($state, $actor, $lines, $billing, $shipping, $method, 9689, $captured, $refunded);
    }
}
