<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Port;

use App\Entity\Store;
use Symfony\Component\Uid\Uuid;

interface StoreRepositoryInterface
{
    public function findById(int $id): ?Store;

    public function findByCode(string $code): ?Store;

    public function findByPublicId(Uuid $publicId): ?Store;

    /** @return list<Store> */
    public function findAllActive(): array;

    /**
     * Primary host of every active store, e.g. [store id => "myoils-auto.shop.test"].
     *
     * @return array<int, string>
     */
    public function findPrimaryHosts(): array;
}
