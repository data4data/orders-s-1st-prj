<?php

declare(strict_types=1);

namespace App\Infrastructure\Platform;

use App\Application\Platform\SystemStatusInterface;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

/**
 * Reads the Doctrine Messenger tables and the webhook log with plain SQL (platform-wide on purpose).
 */
final readonly class DoctrineSystemStatus implements SystemStatusInterface
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
    ) {
    }

    public function queues(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT queue_name, COUNT(*) AS waiting, MIN(created_at) AS oldest FROM messenger_messages WHERE delivered_at IS NULL GROUP BY queue_name ORDER BY queue_name');
        $byQueue = [];
        foreach ($rows as $row) {
            $byQueue[(string) $row['queue_name']] = ['queue' => (string) $row['queue_name'], 'waiting' => (int) $row['waiting'], 'oldest' => null !== $row['oldest'] ? (string) $row['oldest'] : null];
        }

        return array_values(array_map(static fn (string $q) => $byQueue[$q] ?? ['queue' => $q, 'waiting' => 0, 'oldest' => null], array_unique(['async', 'failed', ...array_keys($byQueue)])));
    }

    public function failedMessages(int $limit): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT id, headers, created_at FROM messenger_messages WHERE queue_name = ? ORDER BY id DESC LIMIT '.max(1, $limit), ['failed']);

        return array_map(static function (array $row): array {
            $headers = json_decode((string) $row['headers'], true);
            $headers = \is_array($headers) ? $headers : [];
            $raw = (string) $row['headers'];
            preg_match('/exceptionMessage[^:]*:\s*\\\\?"?[^"]*?"?((?:[^"\\\\]|\\\\.){0,300})/', $raw, $error);

            return [
                'id' => (int) $row['id'],
                'message' => \is_string($headers['type'] ?? null) ? $headers['type'] : 'unknown',
                'error' => isset($error[1]) ? stripslashes($error[1]) : '',
                'failedAt' => (string) $row['created_at'],
            ];
        }, $rows);
    }

    public function retry(int $id): bool
    {
        return 1 === (int) $this->connection->executeStatement(
            'UPDATE messenger_messages SET queue_name = ?, delivered_at = NULL, available_at = ? WHERE id = ? AND queue_name = ?',
            ['async', $this->clock->now()->format('Y-m-d H:i:s'), $id, 'failed'],
        );
    }

    public function delete(int $id): bool
    {
        return 1 === (int) $this->connection->executeStatement('DELETE FROM messenger_messages WHERE id = ? AND queue_name = ?', [$id, 'failed']);
    }

    public function recentWebhooks(int $limit): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT s.name AS store, e.gateway_code, e.external_event_id, e.payload, e.result, e.created_at
             FROM payment_webhook_event e JOIN store s ON s.id = e.store_id ORDER BY e.id DESC LIMIT '.max(1, $limit),
        );

        return array_map(static function (array $row): array {
            $payload = json_decode((string) $row['payload'], true);

            return [
                'store' => (string) $row['store'],
                'gateway' => (string) $row['gateway_code'],
                'eventId' => (string) $row['external_event_id'],
                'type' => \is_array($payload) && \is_string($payload['type'] ?? null) ? $payload['type'] : '',
                'result' => null !== $row['result'] ? (string) $row['result'] : null,
                'receivedAt' => (string) $row['created_at'],
            ];
        }, $rows);
    }
}
