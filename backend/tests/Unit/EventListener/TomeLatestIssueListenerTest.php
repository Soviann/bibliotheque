<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\ComicSeries;
use App\Entity\Tome;
use App\EventListener\TomeLatestIssueListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour TomeLatestIssueListener.
 */
final class TomeLatestIssueListenerTest extends TestCase
{
    private TomeLatestIssueListener $listener;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->listener = new TomeLatestIssueListener();
    }

    public function testPrePersistUpdatesWhenTomeExceedsCurrent(): void
    {
        $series = new ComicSeries();
        $series->setLatestPublishedIssue(5);

        $tome = new Tome();
        $tome->setNumber(8);
        $tome->setComicSeries($series);

        $args = new PrePersistEventArgs($tome, $this->entityManager);
        $this->listener->prePersist($args);

        self::assertSame(8, $series->getLatestPublishedIssue());
    }

    public function testPrePersistDoesNotUpdateWhenTomeBelowCurrent(): void
    {
        $series = new ComicSeries();
        $series->setLatestPublishedIssue(10);

        $tome = new Tome();
        $tome->setNumber(3);
        $tome->setComicSeries($series);

        $args = new PrePersistEventArgs($tome, $this->entityManager);
        $this->listener->prePersist($args);

        self::assertSame(10, $series->getLatestPublishedIssue());
    }

    public function testPrePersistSetsValueWhenCurrentIsNull(): void
    {
        $series = new ComicSeries();

        $tome = new Tome();
        $tome->setNumber(5);
        $tome->setComicSeries($series);

        $args = new PrePersistEventArgs($tome, $this->entityManager);
        $this->listener->prePersist($args);

        self::assertSame(5, $series->getLatestPublishedIssue());
    }

    public function testPrePersistUsesTomeEndWhenSet(): void
    {
        $series = new ComicSeries();
        $series->setLatestPublishedIssue(5);

        $tome = new Tome();
        $tome->setNumber(4);
        $tome->setTomeEnd(8);
        $tome->setComicSeries($series);

        $args = new PrePersistEventArgs($tome, $this->entityManager);
        $this->listener->prePersist($args);

        self::assertSame(8, $series->getLatestPublishedIssue());
    }

    public function testPrePersistIgnoresNonTomeEntity(): void
    {
        $series = new ComicSeries();
        $series->setLatestPublishedIssue(5);

        $args = new PrePersistEventArgs($series, $this->entityManager);
        $this->listener->prePersist($args);

        self::assertSame(5, $series->getLatestPublishedIssue());
    }

    public function testPrePersistIgnoresTomeWithoutSeries(): void
    {
        $tome = new Tome();
        $tome->setNumber(10);

        $args = new PrePersistEventArgs($tome, $this->entityManager);

        // Ne doit pas lever d'exception
        $this->listener->prePersist($args);
        $this->addToAssertionCount(1);
    }

    public function testPreUpdateUpdatesWhenTomeExceedsCurrent(): void
    {
        $series = new ComicSeries();
        $series->setLatestPublishedIssue(5);

        $tome = new Tome();
        $tome->setNumber(12);
        $tome->setComicSeries($series);

        $changeSet = ['number' => [5, 12]];
        $classMetadata = $this->createMock(ClassMetadata::class);
        $args = new PreUpdateEventArgs($tome, $this->entityManager, $changeSet, $classMetadata);
        $this->listener->preUpdate($args);

        self::assertSame(12, $series->getLatestPublishedIssue());
    }
}
