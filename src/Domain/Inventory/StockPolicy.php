<?php

declare(strict_types=1);

namespace App\Domain\Inventory;

/**
 * Checks a whole cart against stock before checkout (the `checkout` workflow guard).
 */
final class StockPolicy
{
    /**
     * @param list<StockRequest> $requests the same SKU may appear more than once
     *
     * @return list<StockShortage> empty when everything can be reserved
     */
    public function shortages(array $requests): array
    {
        $requested = [];
        $available = [];
        foreach ($requests as $request) {
            $requested[$request->sku] = ($requested[$request->sku] ?? 0) + $request->quantity->value;
            $available[$request->sku] = $request->stock->available();
        }

        $shortages = [];
        foreach ($requested as $sku => $quantity) {
            if ($quantity > $available[$sku]) {
                $shortages[] = new StockShortage((string) $sku, $quantity, $available[$sku]);
            }
        }

        return $shortages;
    }

    /**
     * @param list<StockRequest> $requests
     */
    public function canReserveAll(array $requests): bool
    {
        return [] === $this->shortages($requests);
    }
}
