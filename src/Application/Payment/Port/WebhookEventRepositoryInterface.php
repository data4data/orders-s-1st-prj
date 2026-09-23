<?php

declare(strict_types=1);

namespace App\Application\Payment\Port;

use App\Entity\PaymentWebhookEvent;

interface WebhookEventRepositoryInterface
{
    public function find(int $id): ?PaymentWebhookEvent;

    public function exists(string $gatewayCode, string $externalEventId): bool;

    public function save(PaymentWebhookEvent $event): void;
}
