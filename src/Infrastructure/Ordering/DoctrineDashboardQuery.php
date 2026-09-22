<?php

declare(strict_types=1);

namespace App\Infrastructure\Ordering;

use App\Application\Ordering\Port\DashboardQueryInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Plain SQL for the dashboard. DBAL is not covered by the Doctrine tenant filter, so every
 * statement filters by store_id itself.
 */
final readonly class DoctrineDashboardQuery implements DashboardQueryInterface
{
    /** Places where the money has been received (architecture.md §4 → status mapping). */
    private const PAID = ['paid', 'processing', 'shipped', 'delivered'];

    public function __construct(private Connection $connection)
    {
    }

    public function kpis(int $storeId, \DateTimeImmutable $since): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT
                COALESCE(SUM(CASE WHEN state IN (:paid) THEN total_gross END), 0) AS revenue,
                SUM(state IN (:paid)) AS paid_orders,
                COUNT(*) AS placed_orders
             FROM orders WHERE store_id = :store AND state <> \'draft\' AND placed_at >= :since',
            ['paid' => self::PAID, 'store' => $storeId, 'since' => $since->format('Y-m-d H:i:s')],
            ['paid' => ArrayParameterType::STRING],
        ) ?: [];
        $open = $this->connection->fetchAssociative(
            'SELECT SUM(state = \'payment_pending\') AS awaiting, SUM(state IN (\'paid\', \'processing\')) AS to_fulfil FROM orders WHERE store_id = ?',
            [$storeId],
        ) ?: [];

        return [
            'revenue' => (int) ($row['revenue'] ?? 0),
            'paidOrders' => (int) ($row['paid_orders'] ?? 0),
            'placedOrders' => (int) ($row['placed_orders'] ?? 0),
            'awaitingPayment' => (int) ($open['awaiting'] ?? 0),
            'toFulfil' => (int) ($open['to_fulfil'] ?? 0),
        ];
    }

    public function ordersByState(int $storeId): array
    {
        $counts = [];
        foreach ($this->connection->fetchAllAssociative('SELECT state, COUNT(*) AS n FROM orders WHERE store_id = ? AND state <> \'draft\' GROUP BY state', [$storeId]) as $row) {
            $counts[(string) $row['state']] = (int) $row['n'];
        }

        return $counts;
    }

    public function revenueByDay(int $storeId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT DATE(placed_at) AS day, SUM(total_gross) AS revenue, COUNT(*) AS orders
             FROM orders WHERE store_id = :store AND state IN (:paid) AND placed_at >= :from AND placed_at < :to
             GROUP BY DATE(placed_at)',
            ['store' => $storeId, 'paid' => self::PAID, 'from' => $from->format('Y-m-d 00:00:00'), 'to' => $to->modify('+1 day')->format('Y-m-d 00:00:00')],
            ['paid' => ArrayParameterType::STRING],
        );
        $byDay = [];
        foreach ($rows as $row) {
            $byDay[(string) $row['day']] = ['revenue' => (int) $row['revenue'], 'orders' => (int) $row['orders']];
        }

        $days = [];
        for ($day = $from; $day <= $to; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            $days[] = ['day' => $key, 'revenue' => $byDay[$key]['revenue'] ?? 0, 'orders' => $byDay[$key]['orders'] ?? 0];
        }

        return $days;
    }

    public function lowStock(int $storeId, int $threshold, int $limit): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT v.sku, p.name AS product, v.name AS pack, v.on_hand - v.reserved AS available
             FROM product_variant v JOIN product p ON p.id = v.product_id
             WHERE v.store_id = :store AND v.is_active = 1 AND p.is_active = 1 AND v.on_hand - v.reserved <= :threshold
             ORDER BY available ASC, v.sku ASC LIMIT '.max(1, $limit),
            ['store' => $storeId, 'threshold' => $threshold],
        );

        return array_map(static fn (array $r) => ['sku' => (string) $r['sku'], 'product' => (string) $r['product'], 'pack' => (string) $r['pack'], 'available' => (int) $r['available']], $rows);
    }
}
