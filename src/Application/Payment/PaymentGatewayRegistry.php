<?php

declare(strict_types=1);

namespace App\Application\Payment;

use App\Entity\Store;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Finds the gateway of a store (store.payment_gateway_code) among the tagged gateways.
 */
final readonly class PaymentGatewayRegistry
{
    /**
     * @param ServiceProviderInterface<PaymentGatewayInterface> $gateways
     */
    public function __construct(
        #[AutowireLocator('app.payment_gateway')]
        private ServiceProviderInterface $gateways,
    ) {
    }

    public function forStore(Store $store): PaymentGatewayInterface
    {
        return $this->get($store->getPaymentGatewayCode());
    }

    /** @return list<string> installed gateway codes */
    public function codes(): array
    {
        return array_keys($this->gateways->getProvidedServices());
    }

    public function get(string $code): PaymentGatewayInterface
    {
        if (!$this->gateways->has($code)) {
            throw new \LogicException(sprintf('No payment gateway "%s" is installed.', $code));
        }

        return $this->gateways->get($code);
    }
}
