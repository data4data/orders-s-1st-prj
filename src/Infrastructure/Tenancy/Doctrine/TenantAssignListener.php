<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Doctrine;

use App\Application\Tenancy\Exception\MissingTenantException;
use App\Application\Tenancy\TenantContextInterface;
use App\Entity\Contract\TenantAwareInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;

/**
 * Stamps the active store on new tenant entities, and refuses to save rows for another store.
 */
#[AsDoctrineListener(event: Events::prePersist)]
final readonly class TenantAssignListener
{
    public function __construct(private TenantContextInterface $tenantContext)
    {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof TenantAwareInterface) {
            return;
        }

        $activeStore = $this->tenantContext->getStore();
        $ownStore = $entity->getStore();

        if (null === $ownStore) {
            $entity->assignStore($activeStore ?? throw MissingTenantException::forEntity($entity::class));

            return;
        }

        if (null !== $activeStore && $ownStore->getId() !== $activeStore->getId()) {
            throw new \LogicException(sprintf('Refusing to save %s for store "%s" while store "%s" is active.', $entity::class, $ownStore->getCode(), $activeStore->getCode()));
        }
    }
}
