<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Customer\CurrentCustomerInterface;
use App\Entity\Customer;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class SecurityCurrentCustomer implements CurrentCustomerInterface
{
    public function __construct(private Security $security)
    {
    }

    public function get(): ?Customer
    {
        $user = $this->security->getUser();

        return $user instanceof Customer ? $user : null;
    }
}
