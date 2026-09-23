<?php

declare(strict_types=1);

namespace App\Infrastructure\Fixtures\Factory;

use App\Entity\StoreDomain;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<StoreDomain>
 */
final class StoreDomainFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return StoreDomain::class;
    }

    protected function defaults(): array
    {
        return [
            'store' => StoreFactory::new(),
            'host' => self::faker()->unique()->lexify('shop-????').'.shop.test',
            'isPrimary' => true,
        ];
    }
}
