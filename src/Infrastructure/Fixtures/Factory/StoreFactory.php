<?php

declare(strict_types=1);

namespace App\Infrastructure\Fixtures\Factory;

use App\Entity\Store;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

use function Zenstruck\Foundry\lazy;

/**
 * @extends PersistentObjectFactory<Store>
 */
final class StoreFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Store::class;
    }

    public function inactive(): self
    {
        return $this->with(['isActive' => false]);
    }

    protected function defaults(): array
    {
        $code = self::faker()->unique()->lexify('store-????');

        return [
            'code' => $code,
            'name' => "MyOil's ".ucfirst(substr($code, 6)),
            'country' => lazy(static fn () => CountryFactory::netherlands()),
            'currencyCode' => 'EUR',
            'orderNumberPrefix' => strtoupper(substr($code, 6)),
        ];
    }

    protected function initialize(): static
    {
        // Entities have no setters for most fields; set extra attributes via reflection.
        return $this->instantiateWith(Instantiator::withConstructor()->alwaysForce());
    }
}
