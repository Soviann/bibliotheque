<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NotificationPreference;
use App\Entity\User;
use App\Enum\NotificationType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationPreference>
 */
class NotificationPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationPreference::class);
    }

    /**
     * @return list<NotificationPreference>
     */
    public function findByUser(User $user): array
    {
        /** @var list<NotificationPreference> $result */
        $result = $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->orderBy('p.type', 'ASC')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function findByUserAndType(User $user, NotificationType $type): ?NotificationPreference
    {
        /** @var NotificationPreference|null $result */
        $result = $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->andWhere('p.type = :type')
            ->setParameter('type', $type)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }
}
