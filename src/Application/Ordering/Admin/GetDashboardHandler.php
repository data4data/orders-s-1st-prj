<?php

declare(strict_types=1);

namespace App\Application\Ordering\Admin;

use App\Application\Bus\QueryBusInterface;
use App\Application\Ordering\Port\DashboardQueryInterface;
use App\Application\Tenancy\TenantContextInterface;
use App\Domain\Ordering\OrderState;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Admin dashboard (PLAN Phase 6): KPIs of the last 30 days, orders by status, revenue per day
 * (14 days), low stock (store threshold) and the latest orders.
 */
#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetDashboardHandler
{
    public function __construct(
        private TenantContextInterface $tenantContext,
        private DashboardQueryInterface $dashboard,
        private QueryBusInterface $queryBus,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(GetDashboard $query): array
    {
        $store = $this->tenantContext->requireStore();
        $storeId = (int) $store->getId();
        $today = $this->clock->now()->setTime(0, 0);
        $kpis = $this->dashboard->kpis($storeId, $today->modify('-29 days'));
        $byState = $this->dashboard->ordersByState($storeId);
        /** @var array{items: list<array<string, mixed>>} $latest */
        $latest = $this->queryBus->ask(new ListAdminOrders());

        return [
            'currency' => $store->getCurrencyCode(),
            'kpis' => $kpis + ['averageOrder' => $kpis['paidOrders'] > 0 ? intdiv($kpis['revenue'], $kpis['paidOrders']) : 0],
            'ordersByState' => array_values(array_map(
                static fn (OrderState $s) => ['state' => $s->value, 'label' => $s->label(), 'badge' => $s->badge(), 'count' => $byState[$s->value] ?? 0],
                array_filter(OrderState::cases(), static fn (OrderState $s) => OrderState::Draft !== $s),
            )),
            'revenueByDay' => $this->dashboard->revenueByDay($storeId, $today->modify('-13 days'), $today),
            'lowStock' => $this->dashboard->lowStock($storeId, $store->getLowStockThreshold(), 8),
            'lowStockThreshold' => $store->getLowStockThreshold(),
            'latestOrders' => \array_slice($latest['items'], 0, 5),
        ];
    }
}
