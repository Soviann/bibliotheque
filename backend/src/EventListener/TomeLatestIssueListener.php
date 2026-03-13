<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Tome;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Met à jour latestPublishedIssue de la série parente lorsqu'un tome
 * est créé ou modifié avec un numéro supérieur à la valeur actuelle.
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
final class TomeLatestIssueListener
{
    public function prePersist(PrePersistEventArgs $args): void
    {
        $this->updateLatestIssue($args->getObject());
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $this->updateLatestIssue($args->getObject());
    }

    private function updateLatestIssue(object $entity): void
    {
        if (!$entity instanceof Tome) {
            return;
        }

        $series = $entity->getComicSeries();

        if (null === $series) {
            return;
        }

        $highestNumber = $entity->getTomeEnd() ?? $entity->getNumber();
        $current = $series->getLatestPublishedIssue();

        if (null === $current || $highestNumber > $current) {
            $series->setLatestPublishedIssue($highestNumber);
        }
    }
}
