<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup;

use App\Repository\AuthorRepository;
use App\Service\CoverDownloader;
use App\Service\Lookup\LookupApplier;
use App\Service\Lookup\LookupResult;
use App\Tests\Factory\EntityFactory;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour LookupApplier.
 */
final class LookupApplierTest extends TestCase
{
    private AuthorRepository $authorRepository;
    private LookupApplier $applier;
    private CoverDownloader $coverDownloader;

    protected function setUp(): void
    {
        $this->authorRepository = $this->createMock(AuthorRepository::class);
        $this->coverDownloader = $this->createMock(CoverDownloader::class);
        $this->applier = new LookupApplier($this->authorRepository, $this->coverDownloader);
    }

    public function testApplyFillsAllNullFields(): void
    {
        $series = EntityFactory::createComicSeries('Test');
        $result = new LookupResult(
            authors: 'Author A, Author B',
            description: 'Une description',
            isOneShot: true,
            latestPublishedIssue: 10,
            publishedDate: '2024',
            publisher: 'Editeur',
            source: 'test',
            thumbnail: 'https://example.com/cover.jpg',
        );

        $authorA = EntityFactory::createAuthor('Author A');
        $authorB = EntityFactory::createAuthor('Author B');
        $this->authorRepository->method('findOrCreateMultiple')
            ->with(['Author A', 'Author B'])
            ->willReturn([$authorA, $authorB]);

        $updatedFields = $this->applier->apply($series, $result);

        self::assertSame('Une description', $series->getDescription());
        self::assertSame('Editeur', $series->getPublisher());
        self::assertSame('2024', $series->getPublishedDate());
        self::assertSame('https://example.com/cover.jpg', $series->getCoverUrl());
        self::assertTrue($series->isOneShot());
        self::assertSame(10, $series->getLatestPublishedIssue());
        self::assertCount(2, $series->getAuthors());
        self::assertContains('authors', $updatedFields);
        self::assertContains('coverUrl', $updatedFields);
        self::assertContains('description', $updatedFields);
        self::assertContains('isOneShot', $updatedFields);
        self::assertContains('latestPublishedIssue', $updatedFields);
        self::assertContains('publishedDate', $updatedFields);
        self::assertContains('publisher', $updatedFields);
    }

    public function testApplySkipsNonNullFields(): void
    {
        $series = EntityFactory::createComicSeries('Test');
        $series->setDescription('Existing description');
        $series->setPublisher('Existing publisher');

        $result = new LookupResult(
            description: 'New description',
            publisher: 'New publisher',
            source: 'test',
            thumbnail: 'https://example.com/cover.jpg',
        );

        $updatedFields = $this->applier->apply($series, $result);

        // Les champs existants ne sont pas écrasés
        self::assertSame('Existing description', $series->getDescription());
        self::assertSame('Existing publisher', $series->getPublisher());
        // Le champ vide est rempli
        self::assertSame('https://example.com/cover.jpg', $series->getCoverUrl());
        self::assertNotContains('description', $updatedFields);
        self::assertNotContains('publisher', $updatedFields);
        self::assertContains('coverUrl', $updatedFields);
    }

    public function testApplySkipsNullResultFields(): void
    {
        $series = EntityFactory::createComicSeries('Test');
        $result = new LookupResult(source: 'test');

        $updatedFields = $this->applier->apply($series, $result);

        self::assertSame([], $updatedFields);
        self::assertNull($series->getDescription());
        self::assertNull($series->getPublisher());
    }

    public function testApplyAddsAuthorsOnlyWhenEmpty(): void
    {
        $series = EntityFactory::createComicSeries('Test');

        $result = new LookupResult(
            authors: 'Author A, Author B',
            source: 'test',
        );

        $authorA = EntityFactory::createAuthor('Author A');
        $authorB = EntityFactory::createAuthor('Author B');
        $this->authorRepository->method('findOrCreateMultiple')
            ->with(['Author A', 'Author B'])
            ->willReturn([$authorA, $authorB]);

        $updatedFields = $this->applier->apply($series, $result);

        self::assertCount(2, $series->getAuthors());
        self::assertContains('authors', $updatedFields);
    }

    public function testApplySkipsAuthorsWhenSeriesAlreadyHasAuthors(): void
    {
        $series = EntityFactory::createComicSeries('Test');
        $series->addAuthor(EntityFactory::createAuthor('Existing'));

        $result = new LookupResult(
            authors: 'New Author',
            source: 'test',
        );

        $updatedFields = $this->applier->apply($series, $result);

        // La série a déjà des auteurs → on ne les remplace pas
        self::assertCount(1, $series->getAuthors());
        self::assertNotContains('authors', $updatedFields);
    }

    public function testApplySkipsLatestPublishedIssueWhenAlreadySet(): void
    {
        $series = EntityFactory::createComicSeries('Test');
        $series->setLatestPublishedIssue(5);

        $result = new LookupResult(
            latestPublishedIssue: 10,
            source: 'test',
        );

        $updatedFields = $this->applier->apply($series, $result);

        self::assertSame(5, $series->getLatestPublishedIssue());
        self::assertNotContains('latestPublishedIssue', $updatedFields);
    }

    public function testApplySetsLatestPublishedIssueUpdatedAtWhenLatestPublishedIssueUpdated(): void
    {
        $series = EntityFactory::createComicSeries('Test');
        self::assertNull($series->getLatestPublishedIssueUpdatedAt());

        $result = new LookupResult(
            latestPublishedIssue: 10,
            source: 'test',
        );

        $this->applier->apply($series, $result);

        self::assertNotNull($series->getLatestPublishedIssueUpdatedAt());
        self::assertEqualsWithDelta(new \DateTimeImmutable(), $series->getLatestPublishedIssueUpdatedAt(), 5);
    }

    public function testApplyDoesNotSetLatestPublishedIssueUpdatedAtWhenFieldNotUpdated(): void
    {
        $series = EntityFactory::createComicSeries('Test');
        $series->setLatestPublishedIssue(10);

        $result = new LookupResult(
            latestPublishedIssue: 15,
            source: 'test',
        );

        $this->applier->apply($series, $result);

        // latestPublishedIssue was already set → not updated → no date
        self::assertNull($series->getLatestPublishedIssueUpdatedAt());
    }

    public function testApplyCreatesMissingTomesWhenLatestPublishedIssueSet(): void
    {
        $series = EntityFactory::createComicSeries('Test');

        $result = new LookupResult(
            latestPublishedIssue: 3,
            source: 'test',
        );

        $this->applier->apply($series, $result);

        self::assertCount(3, $series->getTomes());

        foreach ($series->getTomes() as $tome) {
            self::assertFalse($tome->isBought());
            self::assertFalse($tome->isDownloaded());
            self::assertFalse($tome->isRead());
        }
    }

    public function testApplyCreatesTomesWithDefaultFlags(): void
    {
        $series = EntityFactory::createComicSeries('Test');
        $series->setDefaultTomeBought(true);
        $series->setDefaultTomeDownloaded(true);
        $series->setDefaultTomeRead(true);

        $result = new LookupResult(
            latestPublishedIssue: 2,
            source: 'test',
        );

        $this->applier->apply($series, $result);

        self::assertCount(2, $series->getTomes());

        foreach ($series->getTomes() as $tome) {
            self::assertTrue($tome->isBought());
            self::assertTrue($tome->isDownloaded());
            self::assertTrue($tome->isRead());
        }
    }

    public function testApplyDoesNotDuplicateExistingTomes(): void
    {
        $series = EntityFactory::createComicSeries('Test');
        $series->addTome(EntityFactory::createTome(1, bought: true));
        $series->addTome(EntityFactory::createTome(2));

        $result = new LookupResult(
            latestPublishedIssue: 4,
            source: 'test',
        );

        $this->applier->apply($series, $result);

        // Tomes 1 et 2 existaient, tomes 3 et 4 sont créés
        self::assertCount(4, $series->getTomes());

        // Le tome 1 existant conserve ses flags
        $tomeNumbers = [];
        foreach ($series->getTomes() as $tome) {
            $tomeNumbers[$tome->getNumber()] = $tome;
        }
        self::assertTrue($tomeNumbers[1]->isBought());
        self::assertFalse($tomeNumbers[3]->isBought());
    }

    public function testApplyDoesNotCreateTomesWhenLatestPublishedIssueNotUpdated(): void
    {
        $series = EntityFactory::createComicSeries('Test');
        $series->setLatestPublishedIssue(5);

        $result = new LookupResult(
            latestPublishedIssue: 10,
            source: 'test',
        );

        $this->applier->apply($series, $result);

        // latestPublishedIssue was already set → not updated → no tomes created
        self::assertCount(0, $series->getTomes());
    }

    public function testApplySkipsIsOneShotWhenAlreadyTrue(): void
    {
        $series = EntityFactory::createComicSeries('Test');
        $series->setIsOneShot(true);

        $result = new LookupResult(
            isOneShot: false,
            source: 'test',
        );

        $updatedFields = $this->applier->apply($series, $result);

        // isOneShot est déjà true → on ne le change pas
        self::assertTrue($series->isOneShot());
        self::assertNotContains('isOneShot', $updatedFields);
    }

    public function testApplyCallsCoverDownloaderWhenThumbnailSet(): void
    {
        $series = EntityFactory::createComicSeries('Test');
        $url = 'https://example.com/cover.jpg';

        $this->coverDownloader->expects(self::once())
            ->method('downloadAndStore')
            ->with($series, $url)
            ->willReturn(true);

        $result = new LookupResult(
            source: 'test',
            thumbnail: $url,
        );

        $this->applier->apply($series, $result);
    }

    public function testApplyDoesNotCallCoverDownloaderWhenNoThumbnail(): void
    {
        $series = EntityFactory::createComicSeries('Test');

        $this->coverDownloader->expects(self::never())
            ->method('downloadAndStore');

        $result = new LookupResult(source: 'test');

        $this->applier->apply($series, $result);
    }

    public function testApplyDoesNotCallCoverDownloaderWhenCoverUrlAlreadySet(): void
    {
        $series = EntityFactory::createComicSeries('Test');
        $series->setCoverUrl('https://existing.com/cover.jpg');

        $this->coverDownloader->expects(self::never())
            ->method('downloadAndStore');

        $result = new LookupResult(
            source: 'test',
            thumbnail: 'https://example.com/new-cover.jpg',
        );

        $this->applier->apply($series, $result);
    }
}
