<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Store;
use App\Infrastructure\Fixtures\CatalogBuilder;
use App\Infrastructure\Fixtures\Factory\CountryFactory;
use App\Infrastructure\Fixtures\Factory\StoreDomainFactory;
use App\Infrastructure\Fixtures\Factory\StoreFactory;
use App\Infrastructure\Fixtures\Story\DemoCatalogs;

/**
 * Test helper: stores with the MyOil's demo catalogs and Dutch VAT.
 */
final class CatalogFixtures
{
    /**
     * @return array{auto: Store, industrie: Store}
     */
    public static function twoShops(CatalogBuilder $builder): array
    {
        $taxCategories = $builder->dutchVat(CountryFactory::netherlands());

        $auto = StoreFactory::createOne(['code' => 'myoils-auto', 'name' => "MyOil's Auto", 'orderNumberPrefix' => 'AUTO']);
        $industrie = StoreFactory::createOne(['code' => 'myoils-industrie', 'name' => "MyOil's Industrie", 'orderNumberPrefix' => 'IND']);
        StoreDomainFactory::createOne(['store' => $auto, 'host' => 'myoils-auto.shop.test']);
        StoreDomainFactory::createOne(['store' => $industrie, 'host' => 'myoils-industrie.shop.test']);

        $builder->build($auto, $taxCategories['standard'], DemoCatalogs::all()['myoils-auto']);
        $builder->build($industrie, $taxCategories['standard'], DemoCatalogs::all()['myoils-industrie']);

        return ['auto' => $auto, 'industrie' => $industrie];
    }
}
