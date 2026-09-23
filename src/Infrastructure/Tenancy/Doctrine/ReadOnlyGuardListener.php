<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Doctrine;

use App\Application\Tenancy\Exception\ReadOnlyTenantException;
use App\Application\Tenancy\TenantContextInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * Blocks every write while the super-admin views "All stores".
 */
#[AsDoctrineListener(event: Events::onFlush)]
final readonly class ReadOnlyGuardListener
{
    public function __construct(private TenantContextInterface $tenantContext)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if (!$this->tenantContext->isReadOnly()) {
            return;
        }

        $unitOfWork = $args->getObjectManager()->getUnitOfWork();
        if ([] !== $unitOfWork->getScheduledEntityInsertions()
            || [] !== $unitOfWork->getScheduledEntityUpdates()
            || [] !== $unitOfWork->getScheduledEntityDeletions()
            || [] !== $unitOfWork->getScheduledCollectionUpdates()
            || [] !== $unitOfWork->getScheduledCollectionDeletions()) {
            throw new ReadOnlyTenantException();
        }
    }
}
