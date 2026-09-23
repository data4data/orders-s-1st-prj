<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Messenger;

use App\Application\Tenancy\Port\StoreRepositoryInterface;
use App\Infrastructure\Tenancy\TenantContext;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * On dispatch: attaches a StoreStamp for the active store.
 * On receive (worker): restores that store around the handler, then resets it.
 */
final readonly class TenantMiddleware implements MiddlewareInterface
{
    public function __construct(
        private TenantContext $tenantContext,
        private StoreRepositoryInterface $stores,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $stamp = $envelope->last(StoreStamp::class);

        if (null === $envelope->last(ReceivedStamp::class)) {
            $store = $this->tenantContext->getStore();
            if (null === $stamp && null !== $store && null !== $store->getId()) {
                $envelope = $envelope->with(new StoreStamp($store->getId()));
            }

            return $stack->next()->handle($envelope, $stack);
        }

        if (null === $stamp) {
            return $stack->next()->handle($envelope, $stack);
        }

        $store = $this->stores->findById($stamp->storeId)
            ?? throw new \RuntimeException(sprintf('Message was sent for store #%d, which no longer exists.', $stamp->storeId));

        return $this->tenantContext->runAsStore($store, static fn (): Envelope => $stack->next()->handle($envelope, $stack));
    }
}
