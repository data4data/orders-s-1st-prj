<?php

declare(strict_types=1);

namespace App\Application\Payment;

use App\Application\Ordering\Port\PaymentRepositoryInterface;
use App\Domain\Money\Money;
use App\Entity\Order;
use App\Entity\Payment;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Starts a payment attempt for an order with the store's gateway (checkout and "try again").
 */
final readonly class PaymentStarter
{
    public function __construct(
        private PaymentGatewayRegistry $gateways,
        private PaymentRepositoryInterface $payments,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function start(Order $order): Payment
    {
        $store = $order->getStore() ?? throw new \LogicException('An order always belongs to a store.');
        $gateway = $this->gateways->forStore($store);
        $payment = new Payment($order, $gateway->code(), $order->getTotalGross(), $order->getCurrencyCode());
        $this->payments->save($payment);

        $session = $gateway->createCheckoutSession(new PaymentRequest(
            $payment->getPublicId()->toRfc4122(),
            (string) $order->getOrderNumber(),
            Money::of($order->getTotalGross(), $order->getCurrencyCode()),
            (string) $order->getCustomerEmail(),
            $this->urls->generate('order_confirmation', ['id' => $order->getPublicId()->toRfc4122()], UrlGeneratorInterface::ABSOLUTE_URL),
            $this->urls->generate('payment_webhook', ['gateway' => $gateway->code()], UrlGeneratorInterface::ABSOLUTE_URL),
        ));
        $payment->attachSession($session->externalReference, $session->redirectUrl, $session->metadata);
        $this->payments->save($payment);

        return $payment;
    }
}
