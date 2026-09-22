<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Settings;

/**
 * Creates (id null) or changes a shipping method.
 */
final readonly class SaveShippingMethod
{
    public function __construct(public ?int $id, public ShippingMethodInput $input)
    {
    }
}
