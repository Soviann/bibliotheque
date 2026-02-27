<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tome;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour l'entité Tome.
 *
 * @extends ServiceEntityRepository<Tome>
 */
class TomeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tome::class);
    }
}
