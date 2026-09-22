<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Runs the messages waiting on the in-memory "async" transport, like `messenger:consume async`.
 * Pass the test container's `messenger.transport.async` and `messenger.routable_message_bus`.
 */
final class AsyncMessages
{
    /**
     * @param class-string|null $only handle only messages of this class (others stay queued)
     *
     * @return int number of handled messages
     */
    public static function consume(InMemoryTransport $transport, MessageBusInterface $bus, ?string $only = null): int
    {
        $handled = 0;
        foreach ($transport->get() as $envelope) {
            if (null !== $only && !$envelope->getMessage() instanceof $only) {
                continue;
            }
            $bus->dispatch($envelope->with(new ReceivedStamp('async')));
            $transport->ack($envelope);
            ++$handled;
        }

        return $handled;
    }
}
