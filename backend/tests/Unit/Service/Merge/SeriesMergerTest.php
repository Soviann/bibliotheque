<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Merge;

use App\DTO\MergePreview;
use App\DTO\MergePreviewTome;
use App\Entity\Author;
use App\Entity\ComicSeries;
use App\Entity\Tome;
use App\Enum\ComicType;
use App\Repository\AuthorRepository;
use App\Repository\ComicSeriesRepository;
use App\Service\Merge\SeriesMerger;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour SeriesMerger.
 */
final class SeriesMergerTest extends TestCase
{
    private AuthorRepository&MockObject $authorRepository;
    private ComicSeriesRepository&MockObject $comicSeriesRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private SeriesMerger $merger;

    protected function setUp(): void
    {
        $this->authorRepository = $this->createMock(AuthorRepository::class);
        $this->comicSeriesRepository = $this->createMock(ComicSeriesRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->merger = new SeriesMerger(
            $this->authorRepository,
            $this->comicSeriesRepository,
            $this->entityManager,
        );
    }

    public function testExecuteMergesThreeOneShotsIntoOneSeries(): void
    {
        $series1 = $this->createSeriesWithId(1, 'Asterix T1');
        $series1->addTome($this->createTomeEntity(1));
        $series2 = $this->createSeriesWithId(2, 'Asterix T2');
        $series2->addTome($this->createTomeEntity(1));
        $series3 = $this->createSeriesWithId(3, 'Asterix T3');
        $series3->addTome($this->createTomeEntity(1));

        $seriesMap = [1 => $series1, 2 => $series2, 3 => $series3];
        $this->comicSeriesRepository->method('find')
            ->willReturnCallback(static fn (int $id): ?ComicSeries => $seriesMap[$id] ?? null);

        $this->authorRepository->method('findOrCreateMultiple')->willReturn([]);

        $removedSeries = [];
        $this->entityManager->method('remove')
            ->willReturnCallback(static function (object $entity) use (&$removedSeries): void {
                if ($entity instanceof ComicSeries) {
                    $removedSeries[] = $entity;
                }
            });
        $this->entityManager->expects(self::once())->method('flush');

        $preview = new MergePreview(
            amazonUrl: null,
            authors: [],
            coverUrl: null,
            defaultTomeBought: false,
            defaultTomeOnNas: false,
            defaultTomeRead: false,
            description: null,
            isOneShot: false,
            latestPublishedIssue: 3,
            latestPublishedIssueComplete: false,
            notInterestedBuy: false,
            notInterestedNas: false,
            publishedDate: null,
            publisher: null,
            sourceSeriesIds: [1, 2, 3],
            status: 'buying',
            title: 'Astérix',
            tomes: [
                new MergePreviewTome(bought: true, isbn: null, number: 1, onNas: false, read: false, title: 'Astérix le Gaulois', tomeEnd: null),
                new MergePreviewTome(bought: true, isbn: null, number: 2, onNas: false, read: false, title: 'La Serpe d\'or', tomeEnd: null),
                new MergePreviewTome(bought: true, isbn: null, number: 3, onNas: false, read: false, title: 'Astérix et les Goths', tomeEnd: null),
            ],
            type: 'bd',
        );

        $result = $this->merger->execute($preview);

        self::assertSame($series1, $result);
        self::assertSame('Astérix', $result->getTitle());
        self::assertCount(3, $result->getTomes());
        self::assertCount(2, $removedSeries);
        self::assertSame($series2, $removedSeries[0]);
        self::assertSame($series3, $removedSeries[1]);
    }

    public function testExecuteUpdatesMetadataFromPreview(): void
    {
        $series = $this->createSeriesWithId(1, 'Old Title');
        $seriesMap = [1 => $series];
        $this->comicSeriesRepository->method('find')
            ->willReturnCallback(static fn (int $id): ?ComicSeries => $seriesMap[$id] ?? null);
        $this->authorRepository->method('findOrCreateMultiple')->willReturn([]);

        $preview = new MergePreview(
            amazonUrl: null,
            authors: [],
            coverUrl: 'https://example.com/cover.jpg',
            defaultTomeBought: false,
            defaultTomeOnNas: false,
            defaultTomeRead: false,
            description: 'Une belle description',
            isOneShot: true,
            latestPublishedIssue: 10,
            latestPublishedIssueComplete: true,
            notInterestedBuy: false,
            notInterestedNas: false,
            publishedDate: null,
            publisher: 'Dargaud',
            sourceSeriesIds: [1],
            status: 'buying',
            title: 'New Title',
            tomes: [],
            type: 'manga',
        );

        $result = $this->merger->execute($preview);

        self::assertSame('New Title', $result->getTitle());
        self::assertSame('Une belle description', $result->getDescription());
        self::assertSame('Dargaud', $result->getPublisher());
        self::assertSame('https://example.com/cover.jpg', $result->getCoverUrl());
        self::assertSame(ComicType::MANGA, $result->getType());
        self::assertTrue($result->isOneShot());
        self::assertSame(10, $result->getLatestPublishedIssue());
        self::assertTrue($result->isLatestPublishedIssueComplete());
    }

    public function testExecuteCreatesCorrectTomes(): void
    {
        $series = $this->createSeriesWithId(1, 'Test');
        $seriesMap = [1 => $series];
        $this->comicSeriesRepository->method('find')
            ->willReturnCallback(static fn (int $id): ?ComicSeries => $seriesMap[$id] ?? null);
        $this->authorRepository->method('findOrCreateMultiple')->willReturn([]);

        $preview = new MergePreview(
            amazonUrl: null,
            authors: [],
            coverUrl: null,
            defaultTomeBought: false,
            defaultTomeOnNas: false,
            defaultTomeRead: false,
            description: null,
            isOneShot: false,
            latestPublishedIssue: null,
            latestPublishedIssueComplete: false,
            notInterestedBuy: false,
            notInterestedNas: false,
            publishedDate: null,
            publisher: null,
            sourceSeriesIds: [1],
            status: 'buying',
            title: 'Test',
            tomes: [
                new MergePreviewTome(bought: true, isbn: '978-2-1234-5678-9', number: 1, onNas: true, read: true, title: 'Premier', tomeEnd: null),
                new MergePreviewTome(bought: false, isbn: null, number: 4, onNas: false, read: false, title: null, tomeEnd: 6),
            ],
            type: 'bd',
        );

        $result = $this->merger->execute($preview);

        $tomes = $result->getTomes()->toArray();
        self::assertCount(2, $tomes);

        $tome1 = $tomes[0];
        self::assertSame(1, $tome1->getNumber());
        self::assertSame('Premier', $tome1->getTitle());
        self::assertSame('978-2-1234-5678-9', $tome1->getIsbn());
        self::assertTrue($tome1->isBought());
        self::assertTrue($tome1->isOnNas());
        self::assertTrue($tome1->isOnNas());
        self::assertTrue($tome1->isRead());
        self::assertNull($tome1->getTomeEnd());

        $tome2 = $tomes[1];
        self::assertSame(4, $tome2->getNumber());
        self::assertNull($tome2->getTitle());
        self::assertNull($tome2->getIsbn());
        self::assertFalse($tome2->isBought());
        self::assertFalse($tome2->isOnNas());
        self::assertFalse($tome2->isOnNas());
        self::assertFalse($tome2->isRead());
        self::assertSame(6, $tome2->getTomeEnd());
    }

    public function testExecuteHandlesAuthors(): void
    {
        $series = $this->createSeriesWithId(1, 'Test');
        // Ajouter un auteur existant pour vérifier qu'il est supprimé
        $oldAuthor = new Author();
        $oldAuthor->setName('Old Author');
        $series->addAuthor($oldAuthor);

        $seriesMap = [1 => $series];
        $this->comicSeriesRepository->method('find')
            ->willReturnCallback(static fn (int $id): ?ComicSeries => $seriesMap[$id] ?? null);

        $author1 = new Author();
        $author1->setName('Goscinny');
        $author2 = new Author();
        $author2->setName('Uderzo');

        $this->authorRepository->method('findOrCreateMultiple')
            ->with(['Goscinny', 'Uderzo'])
            ->willReturn([$author1, $author2]);

        $preview = new MergePreview(
            amazonUrl: null,
            authors: ['Goscinny', 'Uderzo'],
            coverUrl: null,
            defaultTomeBought: false,
            defaultTomeOnNas: false,
            defaultTomeRead: false,
            description: null,
            isOneShot: false,
            latestPublishedIssue: null,
            latestPublishedIssueComplete: false,
            notInterestedBuy: false,
            notInterestedNas: false,
            publishedDate: null,
            publisher: null,
            sourceSeriesIds: [1],
            status: 'buying',
            title: 'Test',
            tomes: [],
            type: 'bd',
        );

        $result = $this->merger->execute($preview);

        $authorNames = $result->getAuthors()->map(static fn (Author $a): string => $a->getName())->toArray();
        self::assertCount(2, $authorNames);
        self::assertContains('Goscinny', $authorNames);
        self::assertContains('Uderzo', $authorNames);
        self::assertNotContains('Old Author', $authorNames);
    }

    public function testExecuteSetsMergeCheckedAt(): void
    {
        $series = $this->createSeriesWithId(1, 'Test');
        $seriesMap = [1 => $series];
        $this->comicSeriesRepository->method('find')
            ->willReturnCallback(static fn (int $id): ?ComicSeries => $seriesMap[$id] ?? null);
        $this->authorRepository->method('findOrCreateMultiple')->willReturn([]);

        self::assertNull($series->getMergeCheckedAt());

        $preview = new MergePreview(
            amazonUrl: null,
            authors: [],
            coverUrl: null,
            defaultTomeBought: false,
            defaultTomeOnNas: false,
            defaultTomeRead: false,
            description: null,
            isOneShot: false,
            latestPublishedIssue: null,
            latestPublishedIssueComplete: false,
            notInterestedBuy: false,
            notInterestedNas: false,
            publishedDate: null,
            publisher: null,
            sourceSeriesIds: [1],
            status: 'buying',
            title: 'Test',
            tomes: [],
            type: 'bd',
        );

        $before = new \DateTimeImmutable();
        $result = $this->merger->execute($preview);

        self::assertNotNull($result->getMergeCheckedAt());
        self::assertGreaterThanOrEqual($before, $result->getMergeCheckedAt());
    }

    private function createSeriesWithId(int $id, string $title): ComicSeries
    {
        $series = new ComicSeries();
        $series->setTitle($title);

        $ref = new \ReflectionProperty(ComicSeries::class, 'id');
        $ref->setValue($series, $id);

        return $series;
    }

    private function createTomeEntity(int $number): Tome
    {
        $tome = new Tome();
        $tome->setNumber($number);

        return $tome;
    }
}
