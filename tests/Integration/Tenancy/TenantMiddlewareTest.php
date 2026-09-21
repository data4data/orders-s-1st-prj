<?php

declare(strict_types=1);

namespace App\Tests\Integration\Tenancy;

use App\Infrastructure\Fixtures\Factory\StoreFactory;
use App\Infrastructure\Tenancy\Messenger\StoreStamp;
use App\Infrastructure\Tenancy\Messenger\TenantMiddleware;
use App\Infrastructure\Tenancy\TenantContext;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class TenantMiddlewareTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testDispatchingAttachesTheActiveStore(): void
    {
        self::bootKernel();
        $store = StoreFactory::createOne();
        $context = self::getContainer()->get(TenantContext::class);
        $context->useStore($store);

        $envelope = $this->middleware()->handle(new Envelope(new \stdClass()), new StackMiddleware());

        self::assertSame($store->getId(), $envelope->last(StoreStamp::class)?->storeId);
    }

    public function testReceivedMessagesRunForTheirStoreAndResetAfterwards(): void
    {
        self::bootKernel();
        $store = StoreFactory::createOne(['code' => 'store-worker']);
        $context = self::getContainer()->get(TenantContext::class);
        $context->clear();

        $spy = new class($context) implements MiddlewareInterface {
            public ?string $storeCodeSeenByHandler = null;

            public function __construct(private readonly TenantContext $context)
            {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                $this->storeCodeSeenByHandler = $this->context->getStore()?->getCode();

                return $envelope;
            }
        };

        $received = (new Envelope(new \stdClass()))->with(new StoreStamp((int) $store->getId()), new ReceivedStamp('async'));
        $this->middleware()->handle($received, new StackMiddleware($spy));

        self::assertSame('store-worker', $spy->storeCodeSeenByHandler);
        self::assertNull($context->getStore(), 'The worker must not keep the store after the message.');
    }

    private function middleware(): TenantMiddleware
    {
        return self::getContainer()->get(TenantMiddleware::class);
    }
}
