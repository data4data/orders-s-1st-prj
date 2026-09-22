<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Ordering\CartProvider;
use App\Entity\Customer;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * After a customer logs in, the cart they filled as a guest becomes (or joins) their own cart.
 */
#[AsEventListener]
final readonly class CustomerLoginListener
{
    public function __construct(private CartProvider $carts)
    {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if ('main' === $event->getFirewallName() && $user instanceof Customer) {
            $this->carts->adoptGuestCart($user);
        }
    }
}
