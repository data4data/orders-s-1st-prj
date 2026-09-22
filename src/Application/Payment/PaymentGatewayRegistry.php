<?php

declare(strict_types=1);

namespace App\Application\Payment;

use App\Entity\Store;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

/**
 * Finds the gateway of a store (store.payment_gateway_code) among the tagged gateways.
 */
final readonly class PaymentGatewayRegistry
{
    public function __construct(
        #[AutowireLocator('app.payment_gateway')]
        private ContainerInterface $gateways,
    ) {
    }

    public function forStore(Store $store): PaymentGatewayInterface
    {
        return $this->get($store->getPaymentGatewayCode());
    }

    public function get(string $code): PaymentGatewayInterface
    {
        if (!$this->gateways->has($code)) {
            throw new \LogicException(sprintf('No payment gateway "%s" is installed.', $code));
        }
        $gateway = $this->gateways->get($code);

        return $gateway instanceof PaymentGatewayInterface ? $gateway : throw new \LogicException(sprintf('Service "%s" is not a payment gateway.', $code));
    }
}
