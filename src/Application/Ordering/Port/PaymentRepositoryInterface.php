<?php

declare(strict_types=1);

namespace App\Application\Ordering\Port;

use App\Entity\Order;
use App\Entity\Payment;

interface PaymentRepositoryInterface
{
    public function latestFor(Order $order): ?Payment;

    /** @return list<Payment> every attempt, oldest first */
    public function forOrder(Order $order): array;

    public function findByReference(string $gatewayCode, string $externalReference): ?Payment;

    public function save(Payment $payment): void;
}
