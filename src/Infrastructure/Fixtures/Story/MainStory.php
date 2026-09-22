<?php

declare(strict_types=1);

namespace App\Infrastructure\Fixtures\Story;

use App\Domain\Tenancy\StoreRole;
use App\Entity\Store;
use App\Infrastructure\Fixtures\CatalogBuilder;
use App\Infrastructure\Fixtures\CheckoutBuilder;
use App\Infrastructure\Fixtures\Factory\CountryFactory;
use App\Infrastructure\Fixtures\Factory\StaffUserFactory;
use App\Infrastructure\Fixtures\Factory\StoreDomainFactory;
use App\Infrastructure\Fixtures\Factory\StoreFactory;
use App\Infrastructure\Fixtures\Factory\StoreMembershipFactory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

/**
 * Demo data: three MyOil's shops in the Netherlands with their catalogs (DemoCatalogs), Dutch VAT,
 * shipping methods and coupons, two staff users and a demo customer (password: "password").
 * More customers and orders are added in Phase 8.
 *
 * Load with: bin/console foundry:load-fixtures main
 */
#[AsFixture(name: 'main')]
final class MainStory extends Story
{
    public function __construct(
        private readonly CatalogBuilder $catalogBuilder,
        private readonly CheckoutBuilder $checkoutBuilder,
    ) {
    }

    public function build(): void
    {
        $taxCategories = $this->catalogBuilder->dutchVat(CountryFactory::netherlands());

        $auto = $this->shop('myoils-auto', "MyOil's Auto", 'AUTO', '#0F2742', '#F2A900');
        $industrie = $this->shop('myoils-industrie', "MyOil's Industrie", 'IND', '#2B2F36', '#F26B1D');
        $agri = $this->shop('myoils-agri', "MyOil's Agri & Marine", 'AGRI', '#1E4D2B', '#F2C230');

        CountryFactory::belgium();
        CountryFactory::germany();
        foreach (['myoils-auto' => $auto, 'myoils-industrie' => $industrie, 'myoils-agri' => $agri] as $code => $store) {
            $this->catalogBuilder->build($store, $taxCategories['standard'], DemoCatalogs::all()[$code]);
            $this->checkoutBuilder->shippingAndCoupons($store, $taxCategories['standard'], pallets: 'myoils-auto' !== $code);
        }

        // The same person has a separate account in each shop (customers are per store, decision #6).
        $this->checkoutBuilder->customer($auto, CountryFactory::netherlands(), 'jan@example.test', 'Jan', 'de Vries',
            ['street' => 'Damrak', 'houseNumber' => '1', 'postcode' => '1012 LG', 'city' => 'Amsterdam'],
            ['label' => 'Garage', 'street' => 'Industrieweg', 'houseNumber' => '12', 'postcode' => '3542 AD', 'city' => 'Utrecht']);
        $this->checkoutBuilder->customer($industrie, CountryFactory::netherlands(), 'jan@example.test', 'Jan', 'de Vries',
            ['street' => 'Havenstraat', 'houseNumber' => '8', 'postcode' => '3024 SH', 'city' => 'Rotterdam', 'company' => 'De Vries Techniek B.V.', 'vatId' => 'NL812345678B01']);

        StaffUserFactory::new()->superAdmin()->create([
            'email' => 'admin@myoils.test',
            'firstName' => 'Platform',
            'lastName' => 'Admin',
        ]);

        // A manager of two of the three shops, to demonstrate store memberships.
        $manager = StaffUserFactory::createOne([
            'email' => 'manager@myoils.test',
            'firstName' => 'Store',
            'lastName' => 'Manager',
        ]);
        foreach ([$auto, $industrie] as $store) {
            StoreMembershipFactory::createOne(['staffUser' => $manager, 'store' => $store, 'role' => StoreRole::Manager]);
        }
    }

    private function shop(string $code, string $name, string $orderPrefix, string $primaryColor, string $accentColor): Store
    {
        $store = StoreFactory::createOne([
            'code' => $code,
            'name' => $name,
            'country' => CountryFactory::netherlands(),
            'orderNumberPrefix' => $orderPrefix,
            'primaryColor' => $primaryColor,
            'accentColor' => $accentColor,
            'contactEmail' => 'info@'.$code.'.test',
        ]);
        StoreDomainFactory::createOne(['store' => $store, 'host' => $code.'.shop.test', 'isPrimary' => true]);

        return $store;
    }
}
