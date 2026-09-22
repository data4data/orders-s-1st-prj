<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Application\Content\Port\ContactMessageRepositoryInterface;
use App\Entity\ContactMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContactMessage>
 */
final class ContactMessageRepository extends ServiceEntityRepository implements ContactMessageRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactMessage::class);
    }

    public function find(mixed $id, $lockMode = null, $lockVersion = null): ?ContactMessage
    {
        return parent::find($id, $lockMode, $lockVersion);
    }

    public function page(bool $unreadOnly, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('m');
        if ($unreadOnly) {
            $qb->where('m.readAt IS NULL');
        }
        $total = (int) (clone $qb)->select('COUNT(m.id)')->getQuery()->getSingleScalarResult();
        $unread = (int) $this->createQueryBuilder('m')->select('COUNT(m.id)')->where('m.readAt IS NULL')->getQuery()->getSingleScalarResult();
        /** @var list<ContactMessage> $items */
        $items = $qb->orderBy('m.createdAt', 'DESC')->addOrderBy('m.id', 'DESC')->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();

        return ['items' => $items, 'total' => $total, 'unread' => $unread];
    }

    public function save(ContactMessage $message): void
    {
        $this->getEntityManager()->persist($message);
        $this->getEntityManager()->flush();
    }
}
