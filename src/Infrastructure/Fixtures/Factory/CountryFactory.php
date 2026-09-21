<?php

declare(strict_types=1);

namespace App\Infrastructure\Fixtures\Factory;

use App\Entity\Country;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Country>
 */
final class CountryFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Country::class;
    }

    public static function netherlands(): Country
    {
        return self::findOrCreate(['code' => 'NL']);
    }

    protected function defaults(): array
    {
        return ['code' => 'NL', 'name' => 'Netherlands', 'isEu' => true];
    }
}
