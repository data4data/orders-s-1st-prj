<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Tenancy\StoreRole;
use App\Entity\StaffUser;
use App\Entity\Store;
use App\Infrastructure\Fixtures\Factory\StaffUserFactory;
use App\Infrastructure\Fixtures\Factory\StoreMembershipFactory;

/**
 * Logs a staff member into the admin with a role in one store and selects that store.
 * For WebTestCase classes that also use JsonApi.
 */
trait AdminLogin
{
    private const ADMIN = 'https://admin.shop.test';

    private function loginAdmin(?Store $store, ?StoreRole $role = StoreRole::Manager, bool $superAdmin = false): StaffUser
    {
        $staff = StaffUserFactory::createOne(['isSuperAdmin' => $superAdmin]);
        if (null !== $store && null !== $role) {
            StoreMembershipFactory::createOne(['staffUser' => $staff, 'store' => $store, 'role' => $role]);
        }
        $this->client->getCookieJar()->clear();
        $this->client->loginUser($staff, 'admin');
        $this->csrfToken = '';
        if (null !== $store) {
            $this->api('PUT', self::ADMIN.'/api/admin/stores/current', ['store' => $store->getPublicId()->toRfc4122()], 204);
        }

        return $staff;
    }
}
