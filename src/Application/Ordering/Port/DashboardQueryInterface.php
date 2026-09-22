<?php

declare(strict_types=1);

namespace App\Application\Ordering\Port;

/**
 * Aggregates for the admin dashboard of one store. Amounts in cents.
 */
interface DashboardQueryInterface
{
    /**
     * @return array{revenue: int, paidOrders: int, placedOrders: int, awaitingPayment: int, toFulfil: int}
     */
    public function kpis(int $storeId, \DateTimeImmutable $since): array;

    /** @return array<string, int> state => number of orders (placed orders only) */
    public function ordersByState(int $storeId): array;

    /** @return list<array{day: string, revenue: int, orders: int}> one row per day, oldest first, days without orders included */
    public function revenueByDay(int $storeId, \DateTimeImmutable $from, \DateTimeImmutable $to): array;

    /** @return list<array{sku: string, product: string, pack: string, available: int}> */
    public function lowStock(int $storeId, int $threshold, int $limit): array;
}
