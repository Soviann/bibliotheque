<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\Author;
use App\Entity\ComicSeries;
use App\Entity\Tome;
use App\Entity\User;
use App\EventListener\ComicSeriesCacheInvalidator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Tests unitaires pour ComicSeriesCacheInvalidator.
 */
final class ComicSeriesCacheInvalidatorTest extends TestCase
{
    private CacheInterface&MockObject $cache;
    private EntityManagerInterface&MockObject $entityManager;
    private UnitOfWork&MockObject $unitOfWork;
    private ComicSeriesCacheInvalidator $listener;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->unitOfWork = $this->createMock(UnitOfWork::class);
        $this->entityManager->method('getUnitOfWork')->willReturn($this->unitOfWork);

        $this->listener = new ComicSeriesCacheInvalidator($this->cache);
    }

    public function testPostPersistWithComicSeriesInvalidatesCache(): void
    {
        $entity = new ComicSeries();
        $event = new PostPersistEventArgs($entity, $this->entityManager);

        $this->cache
            ->expects(self::once())
            ->method('delete')
            ->with('comic_series_api_all');

        $this->listener->postPersist($event);
    }

    public function testPostPersistWithTomeInvalidatesCache(): void
    {
        $entity = new Tome();
        $event = new PostPersistEventArgs($entity, $this->entityManager);

        $this->cache
            ->expects(self::once())
            ->method('delete')
            ->with('comic_series_api_all');

        $this->listener->postPersist($event);
    }

    public function testPostPersistWithAuthorInvalidatesCache(): void
    {
        $entity = new Author();
        $event = new PostPersistEventArgs($entity, $this->entityManager);

        $this->cache
            ->expects(self::once())
            ->method('delete')
            ->with('comic_series_api_all');

        $this->listener->postPersist($event);
    }

    public function testPostPersistWithUnrelatedEntityDoesNotInvalidateCache(): void
    {
        $entity = new User();
        $event = new PostPersistEventArgs($entity, $this->entityManager);

        $this->cache
            ->expects(self::never())
            ->method('delete');

        $this->listener->postPersist($event);
    }

    public function testPostUpdateWithComicSeriesInvalidatesCache(): void
    {
        $entity = new ComicSeries();
        $event = new PostUpdateEventArgs($entity, $this->entityManager);

        $this->unitOfWork
            ->method('getEntityChangeSet')
            ->with($entity)
            ->willReturn(['title' => ['Old', 'New']]);

        $this->cache
            ->expects(self::once())
            ->method('delete')
            ->with('comic_series_api_all');

        $this->listener->postUpdate($event);
    }

    public function testPostUpdateWithTomeInvalidatesCache(): void
    {
        $entity = new Tome();
        $event = new PostUpdateEventArgs($entity, $this->entityManager);

        $this->cache
            ->expects(self::once())
            ->method('delete')
            ->with('comic_series_api_all');

        $this->listener->postUpdate($event);
    }

    public function testPostUpdateWithAuthorInvalidatesCache(): void
    {
        $entity = new Author();
        $event = new PostUpdateEventArgs($entity, $this->entityManager);

        $this->cache
            ->expects(self::once())
            ->method('delete')
            ->with('comic_series_api_all');

        $this->listener->postUpdate($event);
    }

    public function testPostUpdateWithUnrelatedEntityDoesNotInvalidateCache(): void
    {
        $entity = new User();
        $event = new PostUpdateEventArgs($entity, $this->entityManager);

        $this->cache
            ->expects(self::never())
            ->method('delete');

        $this->listener->postUpdate($event);
    }

    public function testPostRemoveWithComicSeriesInvalidatesCache(): void
    {
        $entity = new ComicSeries();
        $event = new PostRemoveEventArgs($entity, $this->entityManager);

        $this->cache
            ->expects(self::once())
            ->method('delete')
            ->with('comic_series_api_all');

        $this->listener->postRemove($event);
    }

    public function testPostRemoveWithTomeInvalidatesCache(): void
    {
        $entity = new Tome();
        $event = new PostRemoveEventArgs($entity, $this->entityManager);

        $this->cache
            ->expects(self::once())
            ->method('delete')
            ->with('comic_series_api_all');

        $this->listener->postRemove($event);
    }

    public function testPostRemoveWithAuthorInvalidatesCache(): void
    {
        $entity = new Author();
        $event = new PostRemoveEventArgs($entity, $this->entityManager);

        $this->cache
            ->expects(self::once())
            ->method('delete')
            ->with('comic_series_api_all');

        $this->listener->postRemove($event);
    }

    public function testPostRemoveWithUnrelatedEntityDoesNotInvalidateCache(): void
    {
        $entity = new User();
        $event = new PostRemoveEventArgs($entity, $this->entityManager);

        $this->cache
            ->expects(self::never())
            ->method('delete');

        $this->listener->postRemove($event);
    }

    public function testPostUpdateWithOnlyInternalFieldsDoesNotInvalidateCache(): void
    {
        $entity = new ComicSeries();
        $event = new PostUpdateEventArgs($entity, $this->entityManager);

        $this->unitOfWork
            ->method('getEntityChangeSet')
            ->with($entity)
            ->willReturn([
                'lookupCompletedAt' => [null, new \DateTimeImmutable()],
                'mergeCheckedAt' => [null, new \DateTimeImmutable()],
            ]);

        $this->cache
            ->expects(self::never())
            ->method('delete');

        $this->listener->postUpdate($event);
    }

    public function testPostUpdateWithPublicFieldInvalidatesCache(): void
    {
        $entity = new ComicSeries();
        $event = new PostUpdateEventArgs($entity, $this->entityManager);

        $this->unitOfWork
            ->method('getEntityChangeSet')
            ->with($entity)
            ->willReturn([
                'lookupCompletedAt' => [null, new \DateTimeImmutable()],
                'title' => ['Old Title', 'New Title'],
            ]);

        $this->cache
            ->expects(self::once())
            ->method('delete')
            ->with('comic_series_api_all');

        $this->listener->postUpdate($event);
    }

    public function testPostUpdateWithNewReleasesCheckedAtOnlyDoesNotInvalidateCache(): void
    {
        $entity = new ComicSeries();
        $event = new PostUpdateEventArgs($entity, $this->entityManager);

        $this->unitOfWork
            ->method('getEntityChangeSet')
            ->with($entity)
            ->willReturn([
                'newReleasesCheckedAt' => [null, new \DateTimeImmutable()],
            ]);

        $this->cache
            ->expects(self::never())
            ->method('delete');

        $this->listener->postUpdate($event);
    }

    public function testPostUpdateWithTomeAlwaysInvalidatesCache(): void
    {
        $entity = new Tome();
        $event = new PostUpdateEventArgs($entity, $this->entityManager);

        $this->cache
            ->expects(self::once())
            ->method('delete')
            ->with('comic_series_api_all');

        $this->listener->postUpdate($event);
    }

    public function testPostUpdateWithAuthorAlwaysInvalidatesCache(): void
    {
        $entity = new Author();
        $event = new PostUpdateEventArgs($entity, $this->entityManager);

        $this->cache
            ->expects(self::once())
            ->method('delete')
            ->with('comic_series_api_all');

        $this->listener->postUpdate($event);
    }
}
