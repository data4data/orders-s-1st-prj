<?php

declare(strict_types=1);

namespace App\Application\Customer\View;

use App\Application\Ordering\View\OrderView;

final readonly class AccountView
{
    /**
     * @param array{email: string, firstName: string, lastName: string, phone: ?string, memberSince: string} $profile
     * @param list<AddressView>                                                                              $addresses
     * @param list<OrderView>                                                                                $recentOrders
     * @param list<array{code: string, name: string}>                                                        $countries
     */
    public function __construct(
        public array $profile,
        public array $addresses,
        public array $recentOrders,
        public int $orderCount,
        public array $countries,
    ) {
    }
}
