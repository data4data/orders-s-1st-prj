<?php

declare(strict_types=1);

namespace App\Application\Ordering;

use App\Application\Ordering\View\OrderView;
use App\Entity\Order;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Order emails to the customer (templates/emails/order_<event>.html.twig).
 */
final readonly class OrderMailer
{
    private const SUBJECTS = [
        'pay' => 'Order %s confirmed: payment received',
        'ship' => 'Order %s is on its way',
        'cancel' => 'Order %s has been cancelled',
        'refund' => 'Order %s has been refunded',
    ];

    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function send(Order $order, string $event): void
    {
        $store = $order->getStore() ?? throw new \LogicException('An order always belongs to a store.');
        $this->mailer->send((new TemplatedEmail())
            ->from(new Address($store->getContactEmail() ?? 'noreply@'.$store->getCode().'.test', $store->getName()))
            ->to(new Address((string) $order->getCustomerEmail(), $order->getBillingAddress()->fullName()))
            ->subject(sprintf(self::SUBJECTS[$event] ?? 'Order %s', $order->getOrderNumber()))
            ->htmlTemplate('emails/order_update.html.twig')
            ->textTemplate('emails/order_update.txt.twig')
            ->context([
                'event' => $event,
                'store_name' => $store->getName(),
                'order' => OrderView::from($order),
                'first_name' => $order->getBillingAddress()->toArray()['firstName'],
                'order_url' => $this->urls->generate('order_confirmation', ['id' => $order->getPublicId()->toRfc4122()], UrlGeneratorInterface::ABSOLUTE_URL),
            ]));
    }
}
