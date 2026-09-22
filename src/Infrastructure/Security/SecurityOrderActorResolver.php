<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Ordering\OrderActor;
use App\Application\Ordering\OrderActorResolverInterface;
use App\Domain\Ordering\ActorType;
use App\Entity\Customer;
use App\Entity\StaffUser;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Staff on the admin host, a customer on the storefront, otherwise the system (workers, webhooks, scheduler).
 */
final readonly class SecurityOrderActorResolver implements OrderActorResolverInterface
{
    public function __construct(private Security $security)
    {
    }

    public function current(): OrderActor
    {
        $user = $this->security->getUser();

        return match (true) {
            $user instanceof StaffUser => new OrderActor(ActorType::Staff, $user->getId(), trim($user->getFirstName().' '.$user->getLastName())),
            $user instanceof Customer => new OrderActor(ActorType::Customer, $user->getId(), trim($user->getFirstName().' '.$user->getLastName())),
            default => new OrderActor(ActorType::System),
        };
    }
}
