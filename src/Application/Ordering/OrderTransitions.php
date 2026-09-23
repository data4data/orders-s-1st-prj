<?php

declare(strict_types=1);

namespace App\Application\Ordering;

use App\Application\Ordering\Port\OrderRepositoryInterface;
use App\Application\Ordering\Port\PaymentRepositoryInterface;
use App\Application\Payment\PaymentGatewayRegistry;
use App\Application\Payment\RefundRequest;
use App\Domain\Money\Money;
use App\Domain\Ordering\ActorType;
use App\Domain\Ordering\OrderState;
use App\Domain\Shared\Quantity;
use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\PaymentRefund;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Applies an order transition together with its effects, in the caller's transaction
 * (architecture.md §4 → stock column of the status mapping):
 * pay → reserved stock is taken out; cancel before payment → reservation released and open
 * payments cancelled; cancel after payment → full refund first, then restock; refund → full refund.
 */
final readonly class OrderTransitions
{
    public function __construct(
        #[Target('order')]
        private WorkflowInterface $orderWorkflow,
        #[Target('payment')]
        private WorkflowInterface $paymentWorkflow,
        private OrderRepositoryInterface $orders,
        private PaymentRepositoryInterface $payments,
        private PaymentGatewayRegistry $gateways,
        private OrderActorResolverInterface $actors,
    ) {
    }

    /**
     * @return list<string> transitions the current actor may apply now
     */
    public function available(Order $order): array
    {
        $names = [];
        foreach ($this->orderWorkflow->getDefinition()->getTransitions() as $transition) {
            $name = $transition->getName();
            if (!\in_array($name, $names, true) && \in_array($order->getState(), $transition->getFroms(), true) && $this->canTry($order, $name)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @throws TransitionNotAllowedException
     */
    public function apply(Order $order, string $transition, ?string $comment = null): void
    {
        $from = $order->state();
        if (!$this->hasEdge($order, $transition) || !$this->canTry($order, $transition)) {
            throw TransitionNotAllowedException::for($transition, $order->getState(), $this->reasons($order, $transition));
        }

        $paid = \in_array($from, [OrderState::Paid, OrderState::Processing, OrderState::Shipped, OrderState::Delivered], true);
        if (('cancel' === $transition && $paid) || 'refund' === $transition) {
            $this->refundEverything($order, $comment ?? ('cancel' === $transition ? 'Order cancelled' : 'Order refunded'));
        }

        if (!$this->orderWorkflow->can($order, $transition)) {
            throw TransitionNotAllowedException::for($transition, $order->getState(), $this->reasons($order, $transition));
        }

        match (true) {
            'pay' === $transition => $this->eachLine($order, static fn ($stock, Quantity $q) => $stock->commit($q)),
            'cancel' === $transition && OrderState::PaymentPending === $from => $this->releaseAndCancelPayments($order),
            'cancel' === $transition && $paid => $this->eachLine($order, static fn ($stock, Quantity $q) => $stock->restock($q)),
            default => null,
        };

        $this->orderWorkflow->apply($order, $transition, ['comment' => $comment]);
        $this->orders->save($order);
    }

    /**
     * Staff may start a cancel or refund of a paid order: the refund happens inside apply(), so the
     * "fully refunded" guard is checked afterwards. Everything else is decided by the guards now.
     */
    private function canTry(Order $order, string $transition): bool
    {
        $paidCancel = 'cancel' === $transition && !\in_array($order->state(), [OrderState::Draft, OrderState::PaymentPending], true);
        if ($paidCancel || 'refund' === $transition) {
            return ActorType::Staff === $this->actors->current()->type;
        }

        return $this->orderWorkflow->can($order, $transition);
    }

    private function hasEdge(Order $order, string $transition): bool
    {
        foreach ($this->orderWorkflow->getDefinition()->getTransitions() as $candidate) {
            if ($candidate->getName() === $transition && \in_array($order->getState(), $candidate->getFroms(), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function reasons(Order $order, string $transition): array
    {
        if (!$this->hasEdge($order, $transition)) {
            return [];
        }
        $reasons = [];
        foreach ($this->orderWorkflow->buildTransitionBlockerList($order, $transition) as $blocker) {
            $reasons[] = $blocker->getMessage();
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param callable(\App\Domain\Inventory\StockLevel, Quantity): \App\Domain\Inventory\StockLevel $change
     */
    private function eachLine(Order $order, callable $change): void
    {
        foreach ($order->getItems() as $item) {
            $variant = $item->getVariant();
            // A pack size deleted after the order was placed has no stock to change.
            $variant?->applyStock($change($variant->stock(), Quantity::of($item->getQuantity())));
        }
    }

    private function releaseAndCancelPayments(Order $order): void
    {
        $this->eachLine($order, static fn ($stock, Quantity $q) => $stock->release($q));
        foreach ($this->payments->forOrder($order) as $payment) {
            if ($this->paymentWorkflow->can($payment, 'cancel')) {
                $this->paymentWorkflow->apply($payment, 'cancel');
            }
        }
    }

    private function refundEverything(Order $order, string $reason): void
    {
        foreach ($this->payments->forOrder($order) as $payment) {
            $remaining = $payment->capturedAmount() - $payment->refundedAmount();
            if ($remaining <= 0) {
                continue;
            }
            $this->refund($payment, $remaining, $reason);
        }
    }

    private function refund(Payment $payment, int $amount, string $reason): void
    {
        $result = $this->gateways->get($payment->getGatewayCode())->refund(
            new RefundRequest((string) $payment->getExternalReference(), Money::of($amount, $payment->getCurrencyCode()), $reason),
        );
        $payment->addRefund(new PaymentRefund($payment, $amount, $reason, $result->succeeded ? PaymentRefund::SUCCEEDED : PaymentRefund::FAILED, $result->externalReference));
        if (!$result->succeeded) {
            throw new TransitionNotAllowedException('The payment provider refused the refund. Nothing was changed.');
        }
        if ($this->paymentWorkflow->can($payment, 'refund')) {
            $this->paymentWorkflow->apply($payment, 'refund');
        }
        $this->payments->save($payment);
    }
}
