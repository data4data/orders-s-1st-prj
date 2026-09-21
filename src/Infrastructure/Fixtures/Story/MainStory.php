<?php

declare(strict_types=1);

namespace App\Infrastructure\Fixtures\Story;

use App\Domain\Tenancy\StoreRole;
use App\Entity\Store;
use App\Infrastructure\Fixtures\Factory\CountryFactory;
use App\Infrastructure\Fixtures\Factory\StaffUserFactory;
use App\Infrastructure\Fixtures\Factory\StoreDomainFactory;
use App\Infrastructure\Fixtures\Factory\StoreFactory;
use App\Infrastructure\Fixtures\Factory\StoreMembershipFactory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

/**
 * Demo data: three MyOil's shops in the Netherlands and two staff users (password: "password").
 * Catalog, customers and orders are added in Phase 8.
 *
 * Load with: bin/console foundry:load-fixtures main
 */
#[AsFixture(name: 'main')]
final class MainStory extends Story
{
    public function build(): void
    {
        $auto = $this->shop('myoils-auto', "MyOil's Auto", 'AUTO', '#0F2742', '#F2A900');
        $industrie = $this->shop('myoils-industrie', "MyOil's Industrie", 'IND', '#2B2F36', '#F26B1D');
        $this->shop('myoils-agri', "MyOil's Agri & Marine", 'AGRI', '#1E4D2B', '#F2C230');

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
