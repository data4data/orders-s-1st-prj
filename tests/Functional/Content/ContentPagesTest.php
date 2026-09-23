<?php

declare(strict_types=1);

namespace App\Tests\Functional\Content;

use App\Entity\ContactMessage;
use App\Infrastructure\Fixtures\CatalogBuilder;
use App\Infrastructure\Fixtures\CheckoutBuilder;
use App\Tests\Support\JsonApi;
use App\Tests\Support\ShopFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ContentPagesTest extends WebTestCase
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

    public function testTheLandingPageShowsCategoriesProductsAndTheOilFinder(): void
    {
        $crawler = $this->client->request('GET', self::AUTO.'/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'The right oil for every engine');
        self::assertSame(['Engine oil', 'Gear & ATF', 'Coolants'], $crawler->filter('.category-tile .fw-semibold')->each(static fn ($n) => $n->text()));
        self::assertStringContainsString('6 products', $crawler->filter('.category-tile')->first()->text());
        self::assertCount(4, $crawler->filter('.product-card'));
        self::assertSame('f[sae_viscosity]', $crawler->filter('[data-oil-finder] select')->attr('name'));
        self::assertStringContainsString('Free from €100.00', $crawler->filter('main')->text());
    }

    public function testEachShopHasItsOwnLandingData(): void
    {
        $crawler = $this->client->request('GET', ShopFixtures::INDUSTRIE.'/');

        self::assertSame('f[iso_vg]', $crawler->filter('[data-oil-finder] select')->attr('name'));
        self::assertStringContainsString('Pallet delivery', $crawler->filter('main')->text());
    }

    public function testTextPagesRender(): void
    {
        foreach (['/about' => 'About us', '/faq' => 'Frequently asked questions', '/terms' => 'Terms and conditions', '/privacy' => 'Privacy statement', '/contact' => 'Contact us'] as $path => $heading) {
            $this->client->request('GET', self::AUTO.$path);
            self::assertResponseIsSuccessful($path);
            self::assertSelectorTextContains('h1', $heading);
        }
        self::assertSelectorExists('form[data-contact-form] input[name="website"]');
    }

    public function testShippingInfoAndSafetyDataSheetsComeFromTheDatabase(): void
    {
        $crawler = $this->client->request('GET', self::AUTO.'/shipping-info');
        $text = $crawler->filter('table')->text();
        self::assertStringContainsString('PostNL Standard', $text);
        self::assertStringContainsString('€6.99', $text);
        self::assertStringContainsString('up to 10 kg: €14.99', $text);
        self::assertStringContainsString('Belgium, Germany', $text);

        $crawler = $this->client->request('GET', self::AUTO.'/safety-data-sheets');
        self::assertGreaterThan(0, $crawler->filter('[data-sds-item]')->count());
        self::assertStringContainsString("MyOil's Synth Pro 5W-30", $crawler->filter('[data-sds-list]')->text());
    }

    public function testTheContactFormStoresTheMessageAndNotifiesTheShop(): void
    {
        $violations = self::violations($this->api('POST', self::AUTO.'/api/contact', ['name' => '', 'email' => 'x', 'subject' => 'order', 'orderNumber' => '123', 'message' => 'short'], 422));
        self::assertSame(['name', 'email', 'orderNumber', 'message'], array_keys($violations));

        $this->api('POST', self::AUTO.'/api/contact', ['name' => 'Kees', 'email' => 'Kees@Example.test', 'subject' => 'order', 'orderNumber' => 'auto-000001', 'message' => 'Where is my order please?'], 204);

        $message = $this->messages()[0];
        self::assertSame(['Kees', 'kees@example.test', 'order', 'AUTO-000001'], [$message->getName(), $message->getEmail(), $message->getSubject(), $message->getOrderNumber()]);
        self::assertQueuedEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailHeaderSame($email, 'To', 'info@myoils-auto.test');
        self::assertEmailHeaderSame($email, 'Reply-To', 'Kees <kees@example.test>');
    }

    public function testBotsAreIgnoredAndTheFormIsRateLimited(): void
    {
        $this->api('POST', self::AUTO.'/api/contact', ['name' => 'Bot', 'email' => 'bot@example.test', 'message' => 'Buy cheap things now!', 'website' => 'https://spam.test'], 204);
        self::assertCount(0, $this->messages());

        // 3 messages per 10 minutes per IP (one was used above).
        $this->api('POST', self::AUTO.'/api/contact', ['name' => 'A', 'email' => 'a@example.test', 'message' => 'First real message'], 204);
        $this->api('POST', self::AUTO.'/api/contact', ['name' => 'A', 'email' => 'a@example.test', 'message' => 'Second real message'], 204);
        $this->api('POST', self::AUTO.'/api/contact', ['name' => 'A', 'email' => 'a@example.test', 'message' => 'Third real message'], 429);
        self::assertResponseHasHeader('Retry-After');
    }

    /**
     * @return list<ContactMessage>
     */
    private function messages(): array
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        if ($em->getFilters()->isEnabled('tenant')) {
            $em->getFilters()->disable('tenant');
        }

        return $em->getRepository(ContactMessage::class)->findAll();
    }
}
