<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup;

use App\Entity\Author;
use App\Entity\ComicSeries;
use App\Repository\AuthorRepository;
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

    protected function setUp(): void
    {
        $this->authorRepository = $this->createMock(AuthorRepository::class);
        $this->applier = new LookupApplier($this->authorRepository);
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
}
