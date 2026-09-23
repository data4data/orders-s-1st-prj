<?php

declare(strict_types=1);

namespace App\Infrastructure\Ordering;

use App\Application\Ordering\OrderNumberGeneratorInterface;
use App\Entity\Store;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * Claims the next per-store order number from store_sequence with SELECT … FOR UPDATE,
 * so two checkouts in the same store never get the same number.
 */
final readonly class DbalOrderNumberGenerator implements OrderNumberGeneratorInterface
{
    private const SEQUENCE = 'order_number';

    public function __construct(private Connection $connection)
    {
    }

    public function next(Store $store): string
    {
        $storeId = $store->getId() ?? throw new \LogicException('Order numbers need a saved store.');

        $number = $this->connection->isTransactionActive()
            ? $this->claim($storeId)
            : $this->connection->transactional(fn (): int => $this->claim($storeId));

        return sprintf('%s-%06d', $store->getOrderNumberPrefix(), $number);
    }

    private function claim(int $storeId): int
    {
        for ($attempt = 1;; ++$attempt) {
            $current = $this->connection->fetchOne(
                'SELECT next_value FROM store_sequence WHERE store_id = ? AND name = ? FOR UPDATE',
                [$storeId, self::SEQUENCE],
            );

            if (false !== $current) {
                $this->connection->update('store_sequence', ['next_value' => (int) $current + 1], ['store_id' => $storeId, 'name' => self::SEQUENCE]);

                return (int) $current;
            }

            try {
                $this->connection->insert('store_sequence', ['store_id' => $storeId, 'name' => self::SEQUENCE, 'next_value' => 2]);

                return 1;
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= 3) {
                    throw $exception;
                }
                // Another request created the row first; read it again with the lock.
            }
        }
    }
}
