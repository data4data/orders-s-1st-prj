<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow;

use App\Application\Ordering\OrderFactsProvider;
use App\Domain\Ordering\OrderTransitionPolicy;
use App\Entity\Order;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\TransitionBlocker;

/**
 * workflow.order.guard: every transition asks the pure OrderTransitionPolicy (architecture.md §4 → Guards).
 */
#[AsEventListener(event: 'workflow.order.guard')]
final readonly class OrderGuardListener
{
    public function __construct(
        private OrderFactsProvider $facts,
        private OrderTransitionPolicy $policy,
    ) {
    }

    public function __invoke(GuardEvent $event): void
    {
        $order = $event->getSubject();
        if (!$order instanceof Order) {
            return;
        }
        foreach ($this->policy->blocks($event->getTransition()->getName(), $this->facts->facts($order)) as $block) {
            $event->addTransitionBlocker(new TransitionBlocker($block->message(), $block->value));
        }
    }
}
