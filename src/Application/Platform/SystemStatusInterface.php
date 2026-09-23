<?php

declare(strict_types=1);

namespace App\Application\Platform;

/**
 * Operational data for Platform → System (queues, failed jobs, webhooks), across all stores.
 */
interface SystemStatusInterface
{
    /** @return list<array{queue: string, waiting: int, oldest: ?string}> */
    public function queues(): array;

    /** @return list<array{id: int, message: string, error: string, failedAt: string}> */
    public function failedMessages(int $limit): array;

    public function retry(int $id): bool;

    public function delete(int $id): bool;

    /** @return list<array{store: string, gateway: string, eventId: string, type: string, result: ?string, receivedAt: string}> */
    public function recentWebhooks(int $limit): array;
}
