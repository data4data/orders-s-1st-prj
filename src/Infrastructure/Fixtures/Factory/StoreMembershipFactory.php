<?php

declare(strict_types=1);

namespace App\Infrastructure\Fixtures\Factory;

use App\Domain\Tenancy\StoreRole;
use App\Entity\StoreMembership;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<StoreMembership>
 */
final class StoreMembershipFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return StoreMembership::class;
    }

    protected function defaults(): array
    {
        return [
            'staffUser' => StaffUserFactory::new(),
            'store' => StoreFactory::new(),
            'role' => StoreRole::Staff,
        ];
    }
}
