<?php

declare(strict_types=1);

namespace App\Application\Payment;

use App\Domain\Money\Money;

/**
 * What a gateway needs to start a payment. The URLs point back to this shop.
 */
final readonly class PaymentRequest
{
    public function __construct(
        public string $paymentId,
        public string $orderNumber,
        public Money $amount,
        public string $customerEmail,
        public string $returnUrl,
        public string $webhookUrl,
    ) {
    }
}
