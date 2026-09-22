<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Customer\CustomerSessionInterface;
use App\Entity\Customer;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Logs a customer into the storefront firewall ("main") without a login form.
 */
final readonly class FirewallCustomerSession implements CustomerSessionInterface
{
    public function __construct(private Security $security)
    {
    }

    public function logIn(Customer $customer): void
    {
        $this->security->login($customer, 'form_login', 'main');
    }
}
