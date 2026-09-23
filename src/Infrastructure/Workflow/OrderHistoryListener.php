<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow;

use App\Application\Ordering\OrderActorResolverInterface;
use App\Entity\Order;
use App\Entity\OrderStatusHistory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * workflow.order.completed: one order_status_history row per transition, with actor and comment.
 * Flushed by the handler together with the transition.
 */
#[AsEventListener(event: 'workflow.order.completed')]
final readonly class OrderHistoryListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrderActorResolverInterface $actors,
    ) {
    }

    public function __invoke(CompletedEvent $event): void
    {
        $order = $event->getSubject();
        $transition = $event->getTransition();
        if (!$order instanceof Order || null === $transition) {
            return;
        }
        $actor = $this->actors->current();
        $comment = $event->getContext()['comment'] ?? null;

        $this->entityManager->persist(new OrderStatusHistory(
            $order,
            $transition->getName(),
            $transition->getFroms()[0] ?? '',
            $order->getState(),
            $actor->type,
            $actor->id,
            $actor->name,
            \is_string($comment) && '' !== trim($comment) ? mb_substr(trim($comment), 0, 500) : null,
        ));
    }
}
