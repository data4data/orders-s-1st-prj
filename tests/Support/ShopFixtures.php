<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Customer;
use App\Entity\Store;
use App\Infrastructure\Fixtures\CatalogBuilder;
use App\Infrastructure\Fixtures\CheckoutBuilder;
use App\Infrastructure\Fixtures\Factory\CountryFactory;
use App\Infrastructure\Fixtures\Factory\StoreDomainFactory;
use App\Infrastructure\Fixtures\Factory\StoreFactory;
use App\Infrastructure\Fixtures\Story\DemoCatalogs;

/**
 * Two shops with catalog, VAT, shipping methods and coupons, and the demo customer in Auto.
 */
final class ShopFixtures
{
    public const AUTO = 'https://myoils-auto.shop.test';
    public const INDUSTRIE = 'https://myoils-industrie.shop.test';

    /**
     * @return array{auto: Store, industrie: Store, jan: Customer}
     */
    public static function create(CatalogBuilder $catalog, CheckoutBuilder $checkout): array
    {
        $nl = CountryFactory::netherlands();
        CountryFactory::belgium();
        CountryFactory::germany();
        $vat = $catalog->dutchVat($nl);

        $shop = static function (string $code, string $name, string $prefix, bool $pallets) use ($nl, $vat, $catalog, $checkout): Store {
            $store = StoreFactory::createOne(['code' => $code, 'name' => $name, 'orderNumberPrefix' => $prefix, 'country' => $nl, 'contactEmail' => 'info@'.$code.'.test']);
            StoreDomainFactory::createOne(['store' => $store, 'host' => $code.'.shop.test', 'isPrimary' => true]);
            $catalog->build($store, $vat['standard'], DemoCatalogs::all()[$code]);
            $checkout->shippingAndCoupons($store, $vat['standard'], $pallets);

            return $store;
        };
        $auto = $shop('myoils-auto', "MyOil's Auto", 'AUTO', false);
        $industrie = $shop('myoils-industrie', "MyOil's Industrie", 'IND', true);

        $jan = $checkout->customer($auto, $nl, 'jan@example.test', 'Jan', 'de Vries',
            ['street' => 'Damrak', 'houseNumber' => '1', 'postcode' => '1012 LG', 'city' => 'Amsterdam'],
            ['label' => 'Garage', 'street' => 'Industrieweg', 'houseNumber' => '12', 'postcode' => '3542 AD', 'city' => 'Utrecht']);

        return ['auto' => $auto, 'industrie' => $industrie, 'jan' => $jan];
    }
}
