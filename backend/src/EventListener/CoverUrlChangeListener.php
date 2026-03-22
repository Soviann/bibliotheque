<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\ComicSeries;
use App\Message\DownloadCoverMessage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Dispatche un message asynchrone pour télécharger la couverture quand coverUrl change.
 */
#[AsDoctrineListener(event: Events::preUpdate)]
final readonly class CoverUrlChangeListener
{
    public function __construct(
        private MessageBusInterface $messageBus,
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

        $seriesId = $entity->getId();

        if (null === $seriesId) {
            return;
        }

        $this->messageBus->dispatch(new DownloadCoverMessage($seriesId, $newUrl));
    }
}
