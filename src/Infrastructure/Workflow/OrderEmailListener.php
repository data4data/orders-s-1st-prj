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
    private const MAILS = ['pay', 'ship', 'cancel', 'refund'];

    public function __construct(private OrderMailer $mailer)
    {
    }

    public function __invoke(CompletedEvent $event): void
    {
        $order = $event->getSubject();
        $transition = $event->getTransition()?->getName();
        if ($order instanceof Order && \in_array($transition, self::MAILS, true) && null !== $order->getCustomerEmail()) {
            $this->mailer->send($order, $transition);
        }
    }
}
