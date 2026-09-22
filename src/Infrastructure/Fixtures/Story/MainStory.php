<?php

declare(strict_types=1);

namespace App\Infrastructure\Fixtures\Story;

use App\Domain\Tenancy\StoreRole;
use App\Entity\StaffUser;
use App\Entity\Store;
use App\Infrastructure\Fixtures\CatalogBuilder;
use App\Infrastructure\Fixtures\CheckoutBuilder;
use App\Infrastructure\Fixtures\DemoOrderBuilder;
use App\Infrastructure\Fixtures\Factory\CountryFactory;
use App\Infrastructure\Fixtures\Factory\StaffUserFactory;
use App\Infrastructure\Fixtures\Factory\StoreDomainFactory;
use App\Infrastructure\Fixtures\Factory\StoreFactory;
use App\Infrastructure\Fixtures\Factory\StoreMembershipFactory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

/**
 * Demo data (PLAN Phase 8): three MyOil's shops in the Netherlands with about 15 products each
 * (DemoCatalogs + DemoCatalogExtras), Dutch VAT with history, shipping methods and coupons, two
 * staff users, five customers per shop (B2C and B2B) and orders in every workflow place with their
 * payments and history. Every password is "password".
 *
 * Load with: bin/console foundry:load-fixtures main
 */
#[AsFixture(name: 'main')]
final class MainStory extends Story
{
    public function __construct(
        private readonly CatalogBuilder $catalogBuilder,
        private readonly CheckoutBuilder $checkoutBuilder,
        private readonly DemoOrderBuilder $orders,
    ) {
    }

    public function build(): void
    {
        $nl = CountryFactory::netherlands();
        $taxCategories = $this->catalogBuilder->dutchVat($nl);

        $auto = $this->shop('myoils-auto', "MyOil's Auto", 'AUTO', '#0F2742', '#F2A900');
        $industrie = $this->shop('myoils-industrie', "MyOil's Industrie", 'IND', '#2B2F36', '#F26B1D');
        $agri = $this->shop('myoils-agri', "MyOil's Agri & Marine", 'AGRI', '#1E4D2B', '#F2C230');

        CountryFactory::belgium();
        CountryFactory::germany();
        foreach (['myoils-auto' => $auto, 'myoils-industrie' => $industrie, 'myoils-agri' => $agri] as $code => $store) {
            $this->catalogBuilder->build($store, $taxCategories['standard'], DemoCatalogExtras::demo()[$code]);
            $this->checkoutBuilder->shippingAndCoupons($store, $taxCategories['standard'], pallets: 'myoils-auto' !== $code);
        }

        $admin = StaffUserFactory::new()->superAdmin()->create([
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

        $this->autoCustomersAndOrders($auto, $manager);
        $this->industrieCustomersAndOrders($industrie, $manager);
        $this->agriCustomersAndOrders($agri, $admin);
    }

    /**
     * Customers per store (decision #6: the same person has a separate account in each shop), B2C and
     * B2B with billing and delivery addresses, and orders in every workflow place.
     */
    private function autoCustomersAndOrders(Store $auto, StaffUser $staff): void
    {
        $nl = CountryFactory::netherlands();
        $jan = $this->checkoutBuilder->customer($auto, $nl, 'jan@example.test', 'Jan', 'de Vries',
            ['street' => 'Damrak', 'houseNumber' => '1', 'postcode' => '1012 LG', 'city' => 'Amsterdam'],
            ['label' => 'Garage', 'street' => 'Industrieweg', 'houseNumber' => '12', 'postcode' => '3542 AD', 'city' => 'Utrecht']);
        $sanne = $this->checkoutBuilder->customer($auto, $nl, 'sanne@example.test', 'Sanne', 'Bakker',
            ['street' => 'Oudegracht', 'houseNumber' => '140', 'postcode' => '3511 AZ', 'city' => 'Utrecht']);
        $visser = $this->checkoutBuilder->customer($auto, $nl, 'inkoop@garagevisser.test', 'Mark', 'Visser',
            ['street' => 'Handelsweg', 'houseNumber' => '4', 'postcode' => '2031 AB', 'city' => 'Haarlem', 'company' => 'Garage Visser B.V.', 'vatId' => 'NL853746291B01'],
            ['label' => 'Workshop', 'street' => 'Werkplaatsstraat', 'houseNumber' => '9', 'postcode' => '2031 AC', 'city' => 'Haarlem']);
        $taxi = $this->checkoutBuilder->customer($auto, $nl, 'wagenpark@taxinoord.test', 'Fatima', 'El Amrani',
            ['street' => 'Stationsplein', 'houseNumber' => '2', 'postcode' => '9726 AE', 'city' => 'Groningen', 'company' => 'Taxi Centrale Noord', 'vatId' => 'NL861234567B01']);
        $mehmet = $this->checkoutBuilder->customer($auto, $nl, 'mehmet@example.test', 'Mehmet', 'Yilmaz',
            ['street' => 'Kruiskade', 'houseNumber' => '55', 'postcode' => '3012 EE', 'city' => 'Rotterdam']);

        $this->orders->build($auto, 'myoils-auto.shop.test', $staff, [
            ['target' => 'delivered', 'customer' => $jan, 'items' => ['SP530-5' => 2, 'BF4-1' => 1], 'daysAgo' => 19],
            ['target' => 'refunded', 'customer' => $sanne, 'items' => ['LL020-5' => 1], 'daysAgo' => 16],
            ['target' => 'delivered', 'customer' => $visser, 'items' => ['PW1040-5' => 6, 'ATFM-1' => 4, 'SCW-5' => 10], 'daysAgo' => 13, 'coupon' => 'WELCOME10'],
            ['target' => 'cancelled_paid', 'customer' => $taxi, 'items' => ['DT530-5' => 4], 'daysAgo' => 11, 'shipping' => 'express'],
            ['target' => 'shipped', 'customer' => $taxi, 'items' => ['DT530-5' => 4, 'INJ-300' => 6], 'daysAgo' => 6],
            ['target' => 'processing', 'customer' => $mehmet, 'items' => ['CL2050-1' => 3], 'daysAgo' => 3, 'coupon' => 'FIVEOFF'],
            ['target' => 'paid', 'customer' => $jan, 'items' => ['EFL-400' => 1, 'SP530-1' => 4], 'daysAgo' => 1],
            ['target' => 'paid', 'guest' => ['email' => 'kees@example.test', 'address' => ['firstName' => 'Kees', 'lastName' => 'Mulder', 'street' => 'Grote Markt', 'houseNumber' => '7', 'postcode' => '9711 LV', 'city' => 'Groningen']], 'items' => ['G12-5' => 2], 'daysAgo' => 0.6],
            ['target' => 'cancelled_unpaid', 'customer' => $mehmet, 'items' => ['GR7590-1' => 2], 'daysAgo' => 8],
            ['target' => 'payment_failed', 'guest' => ['email' => 'lotte@example.test', 'address' => ['firstName' => 'Lotte', 'lastName' => 'Jansen', 'street' => 'Lange Poten', 'houseNumber' => '3', 'postcode' => '2511 CL', 'city' => 'Den Haag']], 'items' => ['ED530-5' => 1], 'daysAgo' => 0.01],
            ['target' => 'payment_pending', 'customer' => $visser, 'items' => ['BF51-05' => 4], 'daysAgo' => 0.005],
            ['target' => 'draft', 'customer' => $sanne, 'items' => ['SCW-5' => 2], 'daysAgo' => 0],
        ]);
    }

    private function industrieCustomersAndOrders(Store $industrie, StaffUser $staff): void
    {
        $nl = CountryFactory::netherlands();
        $jan = $this->checkoutBuilder->customer($industrie, $nl, 'jan@example.test', 'Jan', 'de Vries',
            ['street' => 'Havenstraat', 'houseNumber' => '8', 'postcode' => '3024 SH', 'city' => 'Rotterdam', 'company' => 'De Vries Techniek B.V.', 'vatId' => 'NL812345678B01']);
        $hoek = $this->checkoutBuilder->customer($industrie, $nl, 'onderhoud@machinefabriekhoek.test', 'Ruud', 'Hoek',
            ['street' => 'Fabrieksweg', 'houseNumber' => '21', 'postcode' => '7556 BH', 'city' => 'Hengelo', 'company' => 'Machinefabriek Hoek B.V.', 'vatId' => 'NL804512399B01'],
            ['label' => 'Hal 3', 'street' => 'Fabrieksweg', 'houseNumber' => '23', 'postcode' => '7556 BH', 'city' => 'Hengelo']);
        $cnc = $this->checkoutBuilder->customer($industrie, $nl, 'service@cnc-eindhoven.test', 'Anouk', 'van Dam',
            ['street' => 'Esp', 'houseNumber' => '300', 'postcode' => '5633 AE', 'city' => 'Eindhoven', 'company' => 'CNC Service Eindhoven', 'vatId' => 'NL822233344B01']);
        $groen = $this->checkoutBuilder->customer($industrie, $nl, 'materieel@bouwgroen.test', 'Henk', 'Groen',
            ['street' => 'Bouwstraat', 'houseNumber' => '12', 'postcode' => '3812 NK', 'city' => 'Amersfoort', 'company' => 'Bouwbedrijf Groen', 'vatId' => 'NL835566778B01'],
            ['label' => 'Depot', 'street' => 'Opslagweg', 'houseNumber' => '5', 'postcode' => '3812 NL', 'city' => 'Amersfoort']);
        $lisa = $this->checkoutBuilder->customer($industrie, $nl, 'lisa.smit@example.test', 'Lisa', 'Smit',
            ['street' => 'Molenweg', 'houseNumber' => '17', 'postcode' => '6541 BB', 'city' => 'Nijmegen']);

        $this->orders->build($industrie, 'myoils-industrie.shop.test', $staff, [
            ['target' => 'delivered', 'customer' => $hoek, 'items' => ['HLP46-20' => 5, 'EP2-400' => 24], 'daysAgo' => 20, 'shipping' => 'pallet'],
            ['target' => 'delivered', 'customer' => $cnc, 'items' => ['CUTE-20' => 3, 'SW68-20' => 1], 'daysAgo' => 15, 'shipping' => 'pallet'],
            ['target' => 'refunded', 'customer' => $groen, 'items' => ['HVLP46-20' => 2], 'daysAgo' => 12],
            ['target' => 'shipped', 'customer' => $jan, 'items' => ['VDL100-5' => 2, 'CS46-5' => 1], 'daysAgo' => 5],
            ['target' => 'processing', 'customer' => $hoek, 'items' => ['CLP320-20' => 2], 'daysAgo' => 2, 'shipping' => 'pallet'],
            ['target' => 'paid', 'customer' => $lisa, 'items' => ['EP2-400' => 6, 'SP10-5' => 1], 'daysAgo' => 0.8, 'coupon' => 'WELCOME10'],
            ['target' => 'paid', 'customer' => $cnc, 'items' => ['CUTN22-20' => 1], 'daysAgo' => 0.3],
            ['target' => 'cancelled_paid', 'customer' => $groen, 'items' => ['HEES46-20' => 1], 'daysAgo' => 9],
            ['target' => 'cancelled_unpaid', 'customer' => $lisa, 'items' => ['EP0-18KG' => 1], 'daysAgo' => 7],
            ['target' => 'payment_pending', 'customer' => $jan, 'items' => ['HLP32-20' => 2], 'daysAgo' => 0.005],
            ['target' => 'draft', 'customer' => $hoek, 'items' => ['EP2-18KG' => 1], 'daysAgo' => 0],
        ]);
    }

    private function agriCustomersAndOrders(Store $agri, StaffUser $staff): void
    {
        $nl = CountryFactory::netherlands();
        $deBoer = $this->checkoutBuilder->customer($agri, $nl, 'info@loonbedrijfdeboer.test', 'Gerrit', 'de Boer',
            ['street' => 'Dijkweg', 'houseNumber' => '44', 'postcode' => '8251 PA', 'city' => 'Dronten', 'company' => 'Loonbedrijf De Boer', 'vatId' => 'NL841122334B01'],
            ['label' => 'Loods', 'street' => 'Dijkweg', 'houseNumber' => '46', 'postcode' => '8251 PA', 'city' => 'Dronten']);
        $anker = $this->checkoutBuilder->customer($agri, $nl, 'havenmeester@hetanker.test', 'Sophie', 'Dekker',
            ['street' => 'Havenkade', 'houseNumber' => '1', 'postcode' => '1601 JA', 'city' => 'Enkhuizen', 'company' => 'Jachthaven Het Anker', 'vatId' => 'NL857788990B01']);
        $peters = $this->checkoutBuilder->customer($agri, $nl, 'bas@boomverzorgingpeters.test', 'Bas', 'Peters',
            ['street' => 'Bosrand', 'houseNumber' => '8', 'postcode' => '6711 AA', 'city' => 'Ede', 'company' => 'Boomverzorging Peters']);
        $veenstra = $this->checkoutBuilder->customer($agri, $nl, 'maatschap@veenstra.test', 'Hilde', 'Veenstra',
            ['street' => 'Terpweg', 'houseNumber' => '3', 'postcode' => '9051 BA', 'city' => 'Stiens', 'company' => 'Maatschap Veenstra', 'vatId' => 'NL868877665B01']);
        $pieter = $this->checkoutBuilder->customer($agri, $nl, 'pieter.kuipers@example.test', 'Pieter', 'Kuipers',
            ['street' => 'Zeedijk', 'houseNumber' => '19', 'postcode' => '4501 AA', 'city' => 'Oostburg']);

        $this->orders->build($agri, 'myoils-agri.shop.test', $staff, [
            ['target' => 'delivered', 'customer' => $deBoer, 'items' => ['UTTO-20' => 4, 'SHPD-20' => 3], 'daysAgo' => 18, 'shipping' => 'pallet'],
            ['target' => 'delivered', 'customer' => $anker, 'items' => ['TCW3-1' => 12, 'MGR-400' => 10], 'daysAgo' => 14],
            ['target' => 'refunded', 'customer' => $pieter, 'items' => ['M2T-1' => 2], 'daysAgo' => 12],
            ['target' => 'shipped', 'customer' => $veenstra, 'items' => ['STOU-20' => 2, 'LH46-20' => 1], 'daysAgo' => 4],
            ['target' => 'processing', 'customer' => $peters, 'items' => ['CSB-5' => 4, 'F2T-1' => 6], 'daysAgo' => 2],
            ['target' => 'paid', 'customer' => $anker, 'items' => ['M4T-5' => 3, 'OBG-1' => 4], 'daysAgo' => 0.7, 'coupon' => 'WELCOME10'],
            ['target' => 'paid', 'customer' => $deBoer, 'items' => ['AG8090-20' => 2], 'daysAgo' => 0.2],
            ['target' => 'cancelled_paid', 'customer' => $veenstra, 'items' => ['LS1040-20' => 1], 'daysAgo' => 9],
            ['target' => 'cancelled_unpaid', 'customer' => $pieter, 'items' => ['CHM-5' => 1], 'daysAgo' => 6],
            ['target' => 'payment_failed', 'customer' => $peters, 'items' => ['CHM-5' => 2], 'daysAgo' => 0.01],
            ['target' => 'draft', 'customer' => $pieter, 'items' => ['TCW3-5' => 1], 'daysAgo' => 0],
        ]);
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
