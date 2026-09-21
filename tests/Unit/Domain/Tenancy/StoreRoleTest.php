<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Tenancy;

use App\Domain\Tenancy\StoreRole;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StoreRoleTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('roles')]
    public function testSecurityRolesGrowWithTheRole(StoreRole $role, array $expected): void
    {
        self::assertSame($expected, $role->securityRoles());
    }

    /**
     * @return iterable<string, array{StoreRole, list<string>}>
     */
    public static function roles(): iterable
    {
        yield 'staff' => [StoreRole::Staff, ['ROLE_STORE_STAFF']];
        yield 'manager' => [StoreRole::Manager, ['ROLE_STORE_STAFF', 'ROLE_STORE_MANAGER']];
        yield 'owner' => [StoreRole::Owner, ['ROLE_STORE_STAFF', 'ROLE_STORE_MANAGER', 'ROLE_STORE_OWNER']];
    }
}
