<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Application\Payment\Port\WebhookEventRepositoryInterface;
use App\Entity\PaymentWebhookEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaymentWebhookEvent>
 */
final class WebhookEventRepository extends ServiceEntityRepository implements WebhookEventRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentWebhookEvent::class);
    }

    public function find(mixed $id, $lockMode = null, $lockVersion = null): ?PaymentWebhookEvent
    {
        return parent::find($id, $lockMode, $lockVersion);
    }

    public function exists(string $gatewayCode, string $externalEventId): bool
    {
        return null !== $this->findOneBy(['gatewayCode' => $gatewayCode, 'externalEventId' => $externalEventId]);
    }

    public function save(PaymentWebhookEvent $event): void
    {
        $this->getEntityManager()->persist($event);
        $this->getEntityManager()->flush();
    }
}
