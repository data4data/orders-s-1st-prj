<?php

declare(strict_types=1);

namespace App\Application\Ordering;

use App\Application\Ordering\Port\OrderRepositoryInterface;
use App\Application\Tenancy\Port\StoreRepositoryInterface;
use App\Application\Tenancy\TenantContextInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Cancels stale `payment_pending` orders shop by shop (PLAN Phase 6 → scheduler). Each order is
 * cancelled on its own, so one failure does not stop the others.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class ExpireUnpaidOrdersHandler
{
    public function __construct(
        private StoreRepositoryInterface $stores,
        private TenantContextInterface $tenantContext,
        private OrderRepositoryInterface $orders,
        private OrderTransitions $transitions,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return int number of cancelled orders
     */
    public function __invoke(ExpireUnpaidOrders $command): int
    {
        $cutoff = $this->clock->now()->modify(sprintf('-%d minutes', $command->minutes));
        $cancelled = 0;
        foreach ($this->stores->findAllActive() as $store) {
            $cancelled += $this->tenantContext->runAsStore($store, function () use ($cutoff, $command): int {
                $count = 0;
                foreach ($this->orders->findUnpaidPlacedBefore($cutoff) as $order) {
                    try {
                        $this->transitions->apply($order, 'cancel', sprintf('Payment not received within %d minutes.', $command->minutes));
                        ++$count;
                    } catch (\Throwable $exception) {
                        $this->logger->error('Could not expire order {number}: {message}', ['number' => $order->getOrderNumber(), 'message' => $exception->getMessage()]);
                    }
                }

                return $count;
            });
        }

        return $cancelled;
    }
}
