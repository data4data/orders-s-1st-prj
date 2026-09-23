<?php

declare(strict_types=1);

namespace App\Tests\Functional\Ui;

use App\Infrastructure\Fixtures\Factory\StaffUserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class AdminShellTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testAdminPagesRedirectToTheLoginPage(): void
    {
        $client = self::createClient();

        $client->request('GET', 'https://admin.shop.test/orders');

        self::assertResponseRedirects('/login');
    }

    public function testTheLoginPageLogsStaffIn(): void
    {
        $client = self::createClient();
        StaffUserFactory::createOne(['email' => 'manager@myoils.test', 'firstName' => 'Store']);

        $client->request('GET', 'https://admin.shop.test/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Log in to the admin');

        $client->submitForm('Log in', ['_username' => 'manager@myoils.test', '_password' => StaffUserFactory::PASSWORD]);
        self::assertResponseRedirects('https://admin.shop.test/');

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#admin-app[data-props]');
        self::assertStringContainsString('manager@myoils.test', (string) $client->getCrawler()->filter('#admin-app')->attr('data-props'));
    }

    public function testAWrongPasswordShowsAnError(): void
    {
        $client = self::createClient();
        StaffUserFactory::createOne(['email' => 'manager@myoils.test']);

        $client->request('GET', 'https://admin.shop.test/login');
        $client->submitForm('Log in', ['_username' => 'manager@myoils.test', '_password' => 'wrong']);
        $client->followRedirect();

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.alert-danger', 'The email or password is not correct.');
    }

    public function testEveryAdminUrlServesTheSpaShellAfterLogin(): void
    {
        $client = self::createClient();
        $client->loginUser(StaffUserFactory::createOne(), 'admin');

        $client->request('GET', 'https://admin.shop.test/catalog/products');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#admin-app');
        self::assertSelectorExists('meta[name="csrf-token"]');
    }
}
