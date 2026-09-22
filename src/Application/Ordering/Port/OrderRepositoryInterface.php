<?php

declare(strict_types=1);

namespace App\Application\Ordering\Port;

use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\OrderStatusHistory;
use Symfony\Component\Uid\Uuid;

interface OrderRepositoryInterface
{
    public function findByPublicId(Uuid $publicId): ?Order;

    public function findDraftByPublicId(Uuid $publicId): ?Order;

    /** The newest draft order (cart) of a customer. */
    public function findDraftOfCustomer(Customer $customer): ?Order;

    /**
     * Placed orders of a customer, newest first.
     *
     * @return array{items: list<Order>, total: int}
     */
    public function placedOrdersOf(Customer $customer, int $page, int $perPage): array;

    /**
     * Placed orders for the admin list, newest first.
     *
     * @return array{items: list<Order>, total: int}
     */
    public function adminPage(?string $state, string $search, int $page, int $perPage): array;

    /** @return list<Order> orders still awaiting payment that were placed before $cutoff */
    public function findUnpaidPlacedBefore(\DateTimeImmutable $cutoff): array;

    /** @return list<OrderStatusHistory> oldest first */
    public function history(Order $order): array;

    public function save(Order $order): void;

    public function remove(Order $order): void;
}
