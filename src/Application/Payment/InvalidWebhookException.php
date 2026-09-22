<?php

declare(strict_types=1);

namespace App\Application\Payment;

final class InvalidWebhookException extends \RuntimeException
{
    public static function badSignature(string $gateway): self
    {
        return new self(sprintf('The %s webhook signature is invalid.', $gateway));
    }

    public static function badPayload(string $gateway): self
    {
        return new self(sprintf('The %s webhook payload is invalid.', $gateway));
    }
}
