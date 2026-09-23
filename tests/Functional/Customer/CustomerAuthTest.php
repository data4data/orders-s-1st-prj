<?php

declare(strict_types=1);

namespace App\Tests\Functional\Customer;

use App\Infrastructure\Fixtures\CatalogBuilder;
use App\Infrastructure\Fixtures\CheckoutBuilder;
use App\Tests\Support\JsonApi;
use App\Tests\Support\ShopFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class CustomerAuthTest extends WebTestCase
{
    use Factories;
    use JsonApi;
    use ResetDatabase;

    private const AUTO = ShopFixtures::AUTO;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        ShopFixtures::create(self::getContainer()->get(CatalogBuilder::class), self::getContainer()->get(CheckoutBuilder::class));
    }

    public function testRegisteringWithOneAddressForBillingAndDelivery(): void
    {
        $this->register(['registration[email]' => 'anna@example.test']);
        self::assertResponseRedirects('/account');

        $account = $this->api('GET', self::AUTO.'/api/account');
        self::assertSame('anna@example.test', $account['profile']['email']);
        self::assertCount(1, $account['addresses']);
        $address = $account['addresses'][0];
        self::assertSame([true, true, true, true, '1012 LG'], [$address['usableForBilling'], $address['usableForShipping'], $address['isDefaultBilling'], $address['isDefaultShipping'], $address['postcode']]);
    }

    public function testRegisteringWithASeparateDeliveryAddress(): void
    {
        $this->register([
            'registration[email]' => 'anna@example.test',
            'registration[deliverySameAsBilling]' => false,
            'registration[delivery][firstName]' => 'Anna',
            'registration[delivery][lastName]' => 'Bakker',
            'registration[delivery][street]' => 'Kade',
            'registration[delivery][houseNumber]' => '5',
            'registration[delivery][postcode]' => '3011 AA',
            'registration[delivery][city]' => 'Rotterdam',
        ]);
        self::assertResponseRedirects('/account');

        $addresses = $this->api('GET', self::AUTO.'/api/account')['addresses'];
        self::assertSame([[true, false, 'Amsterdam'], [false, true, 'Rotterdam']], array_map(static fn ($a) => [$a['isDefaultBilling'], $a['isDefaultShipping'], $a['city']], $addresses));
    }

    public function testRegistrationErrorsAreShownOnTheFields(): void
    {
        $this->register(['registration[email]' => 'jan@example.test', 'registration[billing][postcode]' => '12', 'registration[deliverySameAsBilling]' => false]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#registration_billing_postcode ~ .invalid-feedback, .invalid-feedback', 'Enter a Dutch postcode like 1012 AB.');
        // The delivery address is required once "same as billing" is unticked.
        self::assertStringContainsString('Enter the street.', (string) $this->client->getResponse()->getContent());

        $this->register(['registration[email]' => 'jan@example.test']);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('An account with this email already exists.', (string) $this->client->getResponse()->getContent());
    }

    public function testTheSameEmailCanRegisterInAnotherShop(): void
    {
        $this->client->request('GET', ShopFixtures::INDUSTRIE.'/register');
        $this->client->submitForm('Create account', $this->registration(['registration[email]' => 'jan@example.test']));

        self::assertResponseRedirects('/account');
    }

    public function testLoggingInAndOut(): void
    {
        $this->client->request('GET', self::AUTO.'/account');
        self::assertResponseRedirects('/login');

        $this->client->request('GET', self::AUTO.'/login');
        $this->client->submitForm('Log in', ['email' => 'jan@example.test', 'password' => 'wrong']);
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');

        // Accounts are per shop: Jan has no account in Industrie.
        $this->client->request('GET', ShopFixtures::INDUSTRIE.'/login');
        $this->client->submitForm('Log in', ['email' => 'jan@example.test', 'password' => 'password']);
        self::assertResponseRedirects('/login');

        $this->client->request('GET', self::AUTO.'/login');
        $this->client->submitForm('Log in', ['email' => 'jan@example.test', 'password' => 'password']);
        self::assertResponseRedirects('/account');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('"customerName":"Jan"', (string) $this->client->getCrawler()->filter('[data-vue-layout="StorefrontHeader"]')->attr('data-props'));
    }

    public function testTheAccountApiNeedsALogin(): void
    {
        $this->api('GET', self::AUTO.'/api/account', expected: 401);
    }

    public function testResettingAForgottenPassword(): void
    {
        $this->client->request('GET', self::AUTO.'/forgot-password');
        $this->client->submitForm('Send reset link', ['form[email]' => 'nobody@example.test']);
        self::assertResponseRedirects('/login');
        self::assertEmailCount(0);

        $this->client->request('GET', self::AUTO.'/forgot-password');
        $this->client->submitForm('Send reset link', ['form[email]' => 'jan@example.test']);
        self::assertResponseRedirects('/login');
        self::assertQueuedEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertEmailHeaderSame($email, 'To', 'Jan de Vries <jan@example.test>');
        self::assertMatchesRegularExpression('#https://myoils-auto\.shop\.test/reset-password/[A-Za-z0-9._-]+#', (string) $email->getTextBody());
        if (1 !== preg_match('#/reset-password/[A-Za-z0-9._-]+#', (string) $email->getTextBody(), $match)) {
            self::fail('No reset link in the email.');
        }
        $link = $match[0];

        $this->client->request('GET', self::AUTO.$link);
        $this->client->submitForm('Save password', ['form[password]' => 'new-password-1', 'form[repeatPassword]' => 'other']);
        self::assertResponseStatusCodeSame(422);
        $this->client->submitForm('Save password', ['form[password]' => 'new-password-1', 'form[repeatPassword]' => 'new-password-1']);
        self::assertResponseRedirects('/account');

        // The link works once: the password changed, so the signature no longer matches.
        $this->client->request('GET', self::AUTO.$link);
        self::assertResponseStatusCodeSame(410);

        $this->client->restart();
        $this->client->request('GET', self::AUTO.'/login');
        $this->client->submitForm('Log in', ['email' => 'jan@example.test', 'password' => 'new-password-1']);
        self::assertResponseRedirects('/account');
    }

    /**
     * @param array<string, mixed> $values
     */
    private function register(array $values): void
    {
        $this->client->request('GET', self::AUTO.'/register');
        $this->client->submitForm('Create account', $this->registration($values));
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function registration(array $values): array
    {
        return $values + [
            'registration[email]' => 'anna@example.test',
            'registration[password]' => 'secret-pass',
            'registration[firstName]' => 'Anna',
            'registration[lastName]' => 'Bakker',
            'registration[billing][firstName]' => 'Anna',
            'registration[billing][lastName]' => 'Bakker',
            'registration[billing][street]' => 'Damrak',
            'registration[billing][houseNumber]' => '2',
            'registration[billing][postcode]' => '1012lg',
            'registration[billing][city]' => 'Amsterdam',
            'registration[billing][countryCode]' => 'NL',
            'registration[deliverySameAsBilling]' => true,
            'registration[acceptTerms]' => true,
        ];
    }
}
