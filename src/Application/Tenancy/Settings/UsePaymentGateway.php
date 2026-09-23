<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Settings;

/**
 * Chooses the payment gateway of the selected store.
 */
final readonly class UsePaymentGateway
{
    public function __construct(public string $code)
    {
    }
}
