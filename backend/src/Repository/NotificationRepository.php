<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationEntityType;
use App\Enum\NotificationType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * Vérifie si une notification non lue existe pour un type et une entité donnés.
     */
    public function existsUnreadByTypeAndEntity(
        NotificationType $type,
        NotificationEntityType $entityType,
        int $entityId,
    ): bool {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.type = :type')
            ->andWhere('n.relatedEntityType = :entityType')
            ->andWhere('n.relatedEntityId = :entityId')
            ->andWhere('n.read = false')
            ->setParameter('entityId', $entityId)
            ->setParameter('entityType', $entityType)
            ->setParameter('type', $type)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function countUnread(User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.user = :user')
            ->andWhere('n.read = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markAllRead(User $user): int
    {
        /** @var int $result */
        $result = $this->createQueryBuilder('n')
            ->update()
            ->set('n.read', 'true')
            ->andWhere('n.user = :user')
            ->andWhere('n.read = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();

        return $result;
    }

    public function purgeOlderThan(\DateTimeImmutable $before): int
    {
        /** @var int $result */
        $result = $this->createQueryBuilder('n')
            ->delete()
            ->andWhere('n.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();

        return $result;
    }
}
