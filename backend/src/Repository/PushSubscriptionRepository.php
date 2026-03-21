<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PushSubscription;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PushSubscription>
 */
class PushSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PushSubscription::class);
    }

    public function findByEndpoint(string $endpoint): ?PushSubscription
    {
        /** @var PushSubscription|null $result */
        $result = $this->findOneBy(['endpoint' => $endpoint]);

        return $result;
    }

    /**
     * @return list<PushSubscription>
     */
    public function findByUser(User $user): array
    {
        /** @var list<PushSubscription> $result */
        $result = $this->findBy(['user' => $user]);

        return $result;
    }
}
