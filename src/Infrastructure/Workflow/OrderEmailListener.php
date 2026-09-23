<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow;

use App\Application\Ordering\OrderMailer;
use App\Entity\Order;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Customer emails after pay, ship, cancel and refund. Mails go through the async transport.
 */
#[AsEventListener(event: 'workflow.order.completed')]
final readonly class OrderEmailListener
{
    /** Transition => the store's notification switch (Settings → Email notifications). */
    private const MAILS = ['pay' => 'order_paid', 'ship' => 'order_shipped', 'cancel' => 'order_cancelled', 'refund' => 'order_refunded'];

    public function __construct(private OrderMailer $mailer)
    {
    }

    public function __invoke(CompletedEvent $event): void
    {
        $order = $event->getSubject();
        $transition = $event->getTransition()?->getName() ?? '';
        $switch = self::MAILS[$transition] ?? null;
        if ($order instanceof Order && null !== $switch && null !== $order->getCustomerEmail() && true === $order->getStore()?->wantsNotification($switch)) {
            $this->mailer->send($order, $transition);
        }
    }
}
