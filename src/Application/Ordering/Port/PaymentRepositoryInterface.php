<?php

declare(strict_types=1);

namespace App\Application\Ordering\Port;

use App\Entity\Order;
use App\Entity\Payment;

interface PaymentRepositoryInterface
{
    public function latestFor(Order $order): ?Payment;

    public function save(Payment $payment): void;
}
