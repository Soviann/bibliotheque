<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Author;
use App\Entity\ComicSeries;
use App\Entity\Tome;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Invalide le cache de findAllForApi() lors de modifications sur les entités liées.
 *
 * Écoute les événements Doctrine postPersist, postUpdate et postRemove
 * sur ComicSeries, Tome et Author pour supprimer le cache.
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postRemove)]
#[AsDoctrineListener(event: Events::postUpdate)]
class ComicSeriesCacheInvalidator
{
    private const array WATCHED_ENTITIES = [
        Author::class,
        ComicSeries::class,
        Tome::class,
    ];

    public function __construct(
        #[Autowire(service: 'comic_series_api.cache')]
        private readonly CacheInterface $cache,
    ) {
    }

    public function postPersist(PostPersistEventArgs $event): void
    {
        $this->invalidateIfRelevant($event->getObject());
    }

    public function postRemove(PostRemoveEventArgs $event): void
    {
        $this->invalidateIfRelevant($event->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $event): void
    {
        $this->invalidateIfRelevant($event->getObject());
    }

    private function invalidateIfRelevant(object $entity): void
    {
        foreach (self::WATCHED_ENTITIES as $watchedClass) {
            if ($entity instanceof $watchedClass) {
                $this->cache->delete('comic_series_api_all');

                return;
            }
        }
    }
}
