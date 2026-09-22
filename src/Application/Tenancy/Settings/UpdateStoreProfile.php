<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Settings;

/**
 * Store profile and branding.
 */
final readonly class UpdateStoreProfile
{
    public function __construct(public StoreProfileInput $input)
    {
    }
}
