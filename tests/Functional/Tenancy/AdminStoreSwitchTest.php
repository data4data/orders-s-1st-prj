<?php

declare(strict_types=1);

namespace App\Tests\Functional\Tenancy;

use App\Domain\Tenancy\StoreRole;
use App\Entity\StaffUser;
use App\Entity\Store;
use App\Infrastructure\Fixtures\Factory\StaffUserFactory;
use App\Infrastructure\Fixtures\Factory\StoreFactory;
use App\Infrastructure\Fixtures\Factory\StoreMembershipFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class AdminStoreSwitchTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private const ADMIN = 'https://admin.shop.test';

    private KernelBrowser $client;
    private Store $auto;
    private Store $agri;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->auto = StoreFactory::createOne(['code' => 'myoils-auto', 'name' => "MyOil's Auto"]);
        $this->agri = StoreFactory::createOne(['code' => 'myoils-agri', 'name' => "MyOil's Agri"]);
    }

    public function testStaffMustBeLoggedIn(): void
    {
        $this->client->request('GET', self::ADMIN.'/api/admin/stores');

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
    }

    public function testSwitchingWithoutCsrfTokenIsRefusedWith419(): void
    {
        $this->loginAs($this->managerOf($this->auto));

        $this->client->jsonRequest('PUT', self::ADMIN.'/api/admin/stores/current', ['store' => $this->auto->getPublicId()->toRfc4122()]);

        self::assertResponseStatusCodeSame(419);
        self::assertSame(419, $this->json()['status']);
    }

    public function testStaffCanLogInWithJson(): void
    {
        StaffUserFactory::createOne(['email' => 'manager@myoils.test']);

        $this->client->jsonRequest('POST', self::ADMIN.'/api/admin/login', ['username' => 'manager@myoils.test', 'password' => StaffUserFactory::PASSWORD]);

        self::assertResponseIsSuccessful();
        self::assertSame('manager@myoils.test', $this->json()['email']);
    }

    public function testStaffSeeOnlyTheirStores(): void
    {
        $this->loginAs($this->managerOf($this->auto));

        $this->client->request('GET', self::ADMIN.'/api/admin/stores');

        self::assertSame(['myoils-auto'], array_column($this->json(), 'code'));
        self::assertSame('manager', $this->json()[0]['role']);
    }

    public function testStaffCanSwitchToTheirStore(): void
    {
        $this->loginAs($this->managerOf($this->auto));

        $this->switchTo($this->auto->getPublicId()->toRfc4122());
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', self::ADMIN.'/api/admin/stores/current');
        self::assertSame('store', $this->json()['mode']);
        self::assertSame('myoils-auto', $this->json()['store']['code']);
    }

    public function testStaffCannotSwitchToAnotherStore(): void
    {
        $this->loginAs($this->managerOf($this->auto));

        $this->switchTo($this->agri->getPublicId()->toRfc4122());

        self::assertResponseStatusCodeSame(403);
    }

    public function testOnlySuperAdminsCanViewAllStores(): void
    {
        $this->loginAs($this->managerOf($this->auto));
        $this->switchTo('all');
        self::assertResponseStatusCodeSame(403);

        $this->loginAs(StaffUserFactory::new()->superAdmin()->create());
        $this->switchTo('all');
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', self::ADMIN.'/api/admin/stores/current');
        self::assertSame(['mode' => 'all', 'store' => null, 'readOnly' => true], $this->json());
    }

    public function testSuperAdminsSeeEveryActiveStore(): void
    {
        StoreFactory::new()->inactive()->create(['code' => 'closed']);
        $this->loginAs(StaffUserFactory::new()->superAdmin()->create());

        $this->client->request('GET', self::ADMIN.'/api/admin/stores');

        self::assertEqualsCanonicalizing(['myoils-auto', 'myoils-agri'], array_column($this->json(), 'code'));
    }

    public function testAnUnknownStoreIsNotFound(): void
    {
        $this->loginAs($this->managerOf($this->auto));

        $this->switchTo('01a0c5ba-0000-7000-8000-000000000000');

        self::assertResponseStatusCodeSame(404);
    }

    private function managerOf(Store $store): StaffUser
    {
        $staffUser = StaffUserFactory::createOne();
        StoreMembershipFactory::createOne(['staffUser' => $staffUser, 'store' => $store, 'role' => StoreRole::Manager]);

        return $staffUser;
    }

    private function loginAs(StaffUser $staffUser): void
    {
        $this->client->loginUser($staffUser, 'admin');
    }

    private function switchTo(string $store): void
    {
        // State-changing API calls need the CSRF token (ApiCsrfListener).
        $this->client->request('GET', self::ADMIN.'/api/csrf-token');
        $token = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['token'];

        $this->client->jsonRequest('PUT', self::ADMIN.'/api/admin/stores/current', ['store' => $store], ['HTTP_X_CSRF_TOKEN' => $token]);
    }

    /**
     * @return array<mixed>
     */
    private function json(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
