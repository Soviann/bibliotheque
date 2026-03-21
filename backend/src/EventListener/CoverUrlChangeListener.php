<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\ComicSeries;
use App\Service\Cover\CoverDownloader;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Télécharge automatiquement la couverture quand coverUrl change sur une ComicSeries.
 *
 * Utilise preUpdate pour accéder au changeset et modifier l'entité avant le flush.
 */
#[AsDoctrineListener(event: Events::preUpdate)]
final readonly class CoverUrlChangeListener
{
    public function __construct(
        private CoverDownloader $coverDownloader,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof ComicSeries) {
            return;
        }

        if (!$args->hasChangedField('coverUrl')) {
            return;
        }

        $newUrl = $args->getNewValue('coverUrl');
        $oldUrl = $args->getOldValue('coverUrl');

        if (!\is_string($newUrl) || '' === $newUrl || $newUrl === $oldUrl) {
            return;
        }

        if ($this->coverDownloader->downloadAndStore($entity, $newUrl)) {
            // Recalculer le changeset pour inclure coverFile/coverImage
            $this->entityManager->getUnitOfWork()->recomputeSingleEntityChangeSet(
                $this->entityManager->getClassMetadata(ComicSeries::class),
                $entity,
            );
        }
    }
}
