<?php

declare(strict_types=1);

namespace App\Application\Tenancy\View;

/**
 * One entry of the admin store switcher.
 */
final readonly class AdminStoreOption
{
    public function __construct(
        public string $publicId,
        public string $code,
        public string $name,
        public ?string $logoUrl,
        /** The staff member's role in this store, or null for super-admins without a membership. */
        public ?string $role,
    ) {
    }
}
