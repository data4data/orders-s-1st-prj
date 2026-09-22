<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Settings;

/**
 * Switches the store's emails on or off.
 */
final readonly class UpdateNotifications
{
    /**
     * @param array<string, bool> $notifications
     */
    public function __construct(public array $notifications)
    {
    }
}
