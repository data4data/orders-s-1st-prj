<?php

declare(strict_types=1);

namespace App\Application\Payment;

use App\Application\Ordering\OrderTransitions;
use App\Application\Ordering\Port\PaymentRepositoryInterface;
use App\Application\Payment\Port\WebhookEventRepositoryInterface;
use App\Entity\PaymentWebhookEvent;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Webhooks are stored first and deduplicated by (gateway, event id), then processed by a worker
 * (db-schema.html → Payments). Capture moves the order to `pay`, which takes the stock out.
 */
final readonly class PaymentWebhookHandler
{
    public function __construct(
        private PaymentGatewayRegistry $gateways,
        private WebhookEventRepositoryInterface $events,
        private PaymentRepositoryInterface $payments,
        private OrderTransitions $orderTransitions,
        #[Target('payment')]
        private WorkflowInterface $paymentWorkflow,
        // The raw bus: ProcessPaymentWebhook is routed to the async transport, so it has no result.
        private MessageBusInterface $commandBus,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array{received: string, duplicate: bool}
     *
     * @throws InvalidWebhookException
     */
    #[AsMessageHandler(bus: 'command.bus')]
    public function receive(ReceivePaymentWebhook $command): array
    {
        $event = $this->gateways->get($command->gatewayCode)->handleWebhook(new WebhookRequest($command->payload, $command->headers));
        if ($this->events->exists($event->gatewayCode, $event->eventId)) {
            return ['received' => $event->eventId, 'duplicate' => true];
        }

        $stored = new PaymentWebhookEvent($event->gatewayCode, $event->eventId, [
            'type' => $event->type->value,
            'reference' => $event->externalReference,
            'amount' => $event->amount,
            'raw' => $command->payload,
        ]);
        $this->events->save($stored);
        $this->commandBus->dispatch(new ProcessPaymentWebhook((int) $stored->getId()));

        return ['received' => $event->eventId, 'duplicate' => false];
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function process(ProcessPaymentWebhook $command): void
    {
        $event = $this->events->find($command->eventId);
        if (null === $event || $event->isProcessed()) {
            return;
        }
        $data = $event->getPayload();
        $payment = $this->payments->findByReference($event->getGatewayCode(), $data['reference']);
        $transition = PaymentEventType::from($data['type'])->transition();

        $result = match (true) {
            null === $payment => 'ignored: unknown payment',
            null !== $data['amount'] && $data['amount'] !== $payment->getAmount() && 'capture' === $transition => 'ignored: amount does not match',
            !$this->paymentWorkflow->can($payment, $transition) => sprintf('ignored: payment is %s', $payment->getState()),
            default => null,
        };

        if (null === $result) {
            $this->paymentWorkflow->apply($payment, $transition);
            $this->payments->save($payment);
            $result = 'payment '.$payment->getState();
            $order = $payment->getOrder();
            if ('capture' === $transition && \in_array('pay', $this->orderTransitions->available($order), true)) {
                $this->orderTransitions->apply($order, 'pay', 'Payment confirmed by '.$event->getGatewayCode());
                $result .= ', order paid';
            }
        }

        $event->markProcessed($result, $this->clock->now());
        $this->events->save($event);
    }
}
