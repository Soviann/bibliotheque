<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Author;
use App\Entity\ComicSeries;
use App\Entity\Tome;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
use App\Tests\Factory\EntityFactory;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Tests unitaires pour l'entité ComicSeries.
 */
final class ComicSeriesTest extends TestCase
{
    private ?string $tmpFile = null;

    protected function tearDown(): void
    {
        if (null !== $this->tmpFile && \file_exists($this->tmpFile)) {
            \unlink($this->tmpFile);
        }
    }

    // ---------------------------------------------------------------
    // Valeurs par défaut du constructeur
    // ---------------------------------------------------------------

    public function testConstructorDefaults(): void
    {
        $comic = new ComicSeries();

        self::assertNull($comic->getId());
        self::assertSame('', $comic->getTitle());
        self::assertSame(ComicStatus::BUYING, $comic->getStatus());
        self::assertSame(ComicType::BD, $comic->getType());
        self::assertFalse($comic->isDefaultTomeBought());
        self::assertFalse($comic->isDefaultTomeDownloaded());
        self::assertFalse($comic->isDefaultTomeRead());
        self::assertNull($comic->getLatestPublishedIssue());
        self::assertFalse($comic->isLatestPublishedIssueComplete());
        self::assertNull($comic->getLatestPublishedIssueUpdatedAt());
        self::assertFalse($comic->isOneShot());
        self::assertNull($comic->getDescription());
        self::assertNull($comic->getPublishedDate());
        self::assertNull($comic->getPublisher());
        self::assertNull($comic->getCoverImage());
        self::assertNull($comic->getCoverUrl());
        self::assertNull($comic->getCoverFile());
        self::assertInstanceOf(Collection::class, $comic->getAuthors());
        self::assertCount(0, $comic->getAuthors());
        self::assertInstanceOf(Collection::class, $comic->getTomes());
        self::assertCount(0, $comic->getTomes());
        self::assertInstanceOf(\DateTimeImmutable::class, $comic->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $comic->getUpdatedAt());
    }

    // ---------------------------------------------------------------
    // Getters / Setters fluides
    // ---------------------------------------------------------------

    public function testSetTitleReturnsFluent(): void
    {
        $comic = new ComicSeries();
        $result = $comic->setTitle('Astérix');

        self::assertSame($comic, $result);
        self::assertSame('Astérix', $comic->getTitle());
    }

    public function testSetStatusReturnsFluent(): void
    {
        $comic = new ComicSeries();
        $result = $comic->setStatus(ComicStatus::FINISHED);

        self::assertSame($comic, $result);
        self::assertSame(ComicStatus::FINISHED, $comic->getStatus());
    }

    public function testSetTypeReturnsFluent(): void
    {
        $comic = new ComicSeries();
        $result = $comic->setType(ComicType::MANGA);

        self::assertSame($comic, $result);
        self::assertSame(ComicType::MANGA, $comic->getType());
    }

    public function testSetDefaultTomeBoughtReturnsFluent(): void
    {
        $comic = new ComicSeries();
        $result = $comic->setDefaultTomeBought(true);

        self::assertSame($comic, $result);
        self::assertTrue($comic->isDefaultTomeBought());
    }

    public function testSetDefaultTomeDownloadedReturnsFluent(): void
    {
        $comic = new ComicSeries();
        $result = $comic->setDefaultTomeDownloaded(true);

        self::assertSame($comic, $result);
        self::assertTrue($comic->isDefaultTomeDownloaded());
    }

    public function testSetDefaultTomeReadReturnsFluent(): void
    {
        $comic = new ComicSeries();
        $result = $comic->setDefaultTomeRead(true);

        self::assertSame($comic, $result);
        self::assertTrue($comic->isDefaultTomeRead());
    }

    public function testSetLatestPublishedIssueReturnsFluent(): void
    {
        $comic = new ComicSeries();
        $result = $comic->setLatestPublishedIssue(10);

        self::assertSame($comic, $result);
        self::assertSame(10, $comic->getLatestPublishedIssue());
    }

    public function testSetLatestPublishedIssueNull(): void
    {
        $comic = new ComicSeries();
        $comic->setLatestPublishedIssue(5);
        $comic->setLatestPublishedIssue(null);

        self::assertNull($comic->getLatestPublishedIssue());
    }

    public function testSetLatestPublishedIssueCompleteReturnsFluent(): void
    {
        $comic = new ComicSeries();
        $result = $comic->setLatestPublishedIssueComplete(true);

        self::assertSame($comic, $result);
        self::assertTrue($comic->isLatestPublishedIssueComplete());
    }

    public function testSetLatestPublishedIssueUpdatedAtReturnsFluent(): void
    {
        $comic = new ComicSeries();
        $date = new \DateTimeImmutable('2025-06-01');
        $result = $comic->setLatestPublishedIssueUpdatedAt($date);

        self::assertSame($comic, $result);
        self::assertSame($date, $comic->getLatestPublishedIssueUpdatedAt());
    }

    public function testSetLatestPublishedIssueUpdatedAtNull(): void
    {
        $comic = new ComicSeries();
        $comic->setLatestPublishedIssueUpdatedAt(new \DateTimeImmutable());
        $comic->setLatestPublishedIssueUpdatedAt(null);

        self::assertNull($comic->getLatestPublishedIssueUpdatedAt());
    }

    public function testSetIsOneShotReturnsFluent(): void
    {
        $comic = new ComicSeries();
        $result = $comic->setIsOneShot(true);

        self::assertSame($comic, $result);
        self::assertTrue($comic->isOneShot());
    }

    public function testSetDescriptionReturnsFluent(): void
    {
        $comic = new ComicSeries();
        $result = $comic->setDescription('Une description');

        self::assertSame($comic, $result);
        self::assertSame('Une description', $comic->getDescription());
    }

    public function testSetDescriptionNull(): void
    {
        $comic = new ComicSeries();
        $comic->setDescription('Texte');
        $comic->setDescription(null);

        self::assertNull($comic->getDescription());
    }

    public function testSetPublishedDateReturnsFluent(): void
    {
        $comic = new ComicSeries();
        $result = $comic->setPublishedDate('2024-01-15');

        self::assertSame($comic, $result);
        self::assertSame('2024-01-15', $comic->getPublishedDate());
    }

    public function testSetPublisherReturnsFluent(): void
    {
        $comic = new ComicSeries();
        $result = $comic->setPublisher('Dargaud');

        self::assertSame($comic, $result);
        self::assertSame('Dargaud', $comic->getPublisher());
    }

    public function testSetCoverImageReturnsFluent(): void
    {
        $comic = new ComicSeries();
        $result = $comic->setCoverImage('cover.jpg');

        self::assertSame($comic, $result);
        self::assertSame('cover.jpg', $comic->getCoverImage());
    }

    public function testSetCoverImageNull(): void
    {
        $comic = new ComicSeries();
        $comic->setCoverImage('cover.jpg');
        $comic->setCoverImage(null);

        self::assertNull($comic->getCoverImage());
    }

    public function testSetCoverUrlReturnsFluent(): void
    {
        $comic = new ComicSeries();
        $result = $comic->setCoverUrl('https://example.com/cover.jpg');

        self::assertSame($comic, $result);
        self::assertSame('https://example.com/cover.jpg', $comic->getCoverUrl());
    }

    public function testSetCoverUrlNull(): void
    {
        $comic = new ComicSeries();
        $comic->setCoverUrl('https://example.com/cover.jpg');
        $comic->setCoverUrl(null);

        self::assertNull($comic->getCoverUrl());
    }

    public function testSetCreatedAtReturnsFluent(): void
    {
        $comic = new ComicSeries();
        $date = new \DateTimeImmutable('2024-06-01');
        $result = $comic->setCreatedAt($date);

        self::assertSame($comic, $result);
        self::assertSame($date, $comic->getCreatedAt());
    }

    public function testSetUpdatedAtReturnsFluent(): void
    {
        $comic = new ComicSeries();
        $date = new \DateTimeImmutable('2024-06-01');
        $result = $comic->setUpdatedAt($date);

        self::assertSame($comic, $result);
        self::assertSame($date, $comic->getUpdatedAt());
    }

    // ---------------------------------------------------------------
    // setCoverFile
    // ---------------------------------------------------------------

    public function testSetCoverFileWithFileUpdatesUpdatedAt(): void
    {
        $comic = new ComicSeries();
        $originalUpdatedAt = $comic->getUpdatedAt();

        // On attend un tick pour garantir un timestamp différent
        \usleep(1000);

        $this->tmpFile = \tempnam(\sys_get_temp_dir(), 'test_cover_');
        \file_put_contents($this->tmpFile, 'fake image content');
        $file = new File($this->tmpFile);
        $result = $comic->setCoverFile($file);

        self::assertSame($comic, $result);
        self::assertSame($file, $comic->getCoverFile());
        self::assertGreaterThan($originalUpdatedAt, $comic->getUpdatedAt());
    }

    public function testSetCoverFileWithNullDoesNotChangeUpdatedAt(): void
    {
        $comic = new ComicSeries();
        $fixedDate = new \DateTimeImmutable('2024-01-01 00:00:00');
        $comic->setUpdatedAt($fixedDate);

        $comic->setCoverFile(null);

        self::assertNull($comic->getCoverFile());
        self::assertSame($fixedDate, $comic->getUpdatedAt());
    }

    // ---------------------------------------------------------------
    // Authors
    // ---------------------------------------------------------------

    public function testAddAuthorAddsToCollection(): void
    {
        $comic = EntityFactory::createComicSeries();
        $author = EntityFactory::createAuthor('Uderzo');

        $result = $comic->addAuthor($author);

        self::assertSame($comic, $result);
        self::assertCount(1, $comic->getAuthors());
        self::assertTrue($comic->getAuthors()->contains($author));
    }

    public function testAddAuthorNoDuplicates(): void
    {
        $comic = EntityFactory::createComicSeries();
        $author = EntityFactory::createAuthor('Uderzo');

        $comic->addAuthor($author);
        $comic->addAuthor($author);

        self::assertCount(1, $comic->getAuthors());
    }

    public function testRemoveAuthor(): void
    {
        $comic = EntityFactory::createComicSeries();
        $author = EntityFactory::createAuthor('Uderzo');

        $comic->addAuthor($author);
        $result = $comic->removeAuthor($author);

        self::assertSame($comic, $result);
        self::assertCount(0, $comic->getAuthors());
        self::assertFalse($comic->getAuthors()->contains($author));
    }

    public function testRemoveAuthorNotInCollection(): void
    {
        $comic = EntityFactory::createComicSeries();
        $author = EntityFactory::createAuthor('Uderzo');

        $result = $comic->removeAuthor($author);

        self::assertSame($comic, $result);
        self::assertCount(0, $comic->getAuthors());
    }

    // ---------------------------------------------------------------
    // Tomes
    // ---------------------------------------------------------------

    public function testAddTomeAddsToCollectionAndSetsComicSeries(): void
    {
        $comic = EntityFactory::createComicSeries();
        $tome = EntityFactory::createTome(1);

        $result = $comic->addTome($tome);

        self::assertSame($comic, $result);
        self::assertCount(1, $comic->getTomes());
        self::assertTrue($comic->getTomes()->contains($tome));
        self::assertSame($comic, $tome->getComicSeries());
    }

    public function testAddTomeNoDuplicates(): void
    {
        $comic = EntityFactory::createComicSeries();
        $tome = EntityFactory::createTome(1);

        $comic->addTome($tome);
        $comic->addTome($tome);

        self::assertCount(1, $comic->getTomes());
    }

    public function testRemoveTomeSetsComicSeriesToNull(): void
    {
        $comic = EntityFactory::createComicSeries();
        $tome = EntityFactory::createTome(1);

        $comic->addTome($tome);
        $result = $comic->removeTome($tome);

        self::assertSame($comic, $result);
        self::assertCount(0, $comic->getTomes());
        self::assertNull($tome->getComicSeries());
    }

    public function testRemoveTomeNotInCollection(): void
    {
        $comic = EntityFactory::createComicSeries();
        $tome = EntityFactory::createTome(1);

        $result = $comic->removeTome($tome);

        self::assertSame($comic, $result);
        self::assertCount(0, $comic->getTomes());
    }

    // ---------------------------------------------------------------
    // getAuthorsAsString
    // ---------------------------------------------------------------

    public function testGetAuthorsAsStringEmpty(): void
    {
        $comic = EntityFactory::createComicSeries();

        self::assertSame('', $comic->getAuthorsAsString());
    }

    public function testGetAuthorsAsStringOneAuthor(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->addAuthor(EntityFactory::createAuthor('Goscinny'));

        self::assertSame('Goscinny', $comic->getAuthorsAsString());
    }

    public function testGetAuthorsAsStringMultipleAuthors(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->addAuthor(EntityFactory::createAuthor('Goscinny'));
        $comic->addAuthor(EntityFactory::createAuthor('Uderzo'));

        self::assertSame('Goscinny, Uderzo', $comic->getAuthorsAsString());
    }

    // ---------------------------------------------------------------
    // getCurrentIssue
    // ---------------------------------------------------------------

    public function testGetCurrentIssueEmptyTomesReturnsNull(): void
    {
        $comic = EntityFactory::createComicSeries();

        self::assertNull($comic->getCurrentIssue());
    }

    public function testGetCurrentIssueReturnsMaxTomeNumber(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->addTome(EntityFactory::createTome(3));
        $comic->addTome(EntityFactory::createTome(1));
        $comic->addTome(EntityFactory::createTome(5));

        self::assertSame(5, $comic->getCurrentIssue());
    }

    // ---------------------------------------------------------------
    // getLastBought / getLastDownloaded / getLastRead
    // ---------------------------------------------------------------

    public function testGetLastBoughtNoTomesReturnsNull(): void
    {
        $comic = EntityFactory::createComicSeries();

        self::assertNull($comic->getLastBought());
    }

    public function testGetLastBoughtNoneBoughtReturnsNull(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->addTome(EntityFactory::createTome(1, bought: false));

        self::assertNull($comic->getLastBought());
    }

    public function testGetLastBoughtReturnsMaxBoughtNumber(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->addTome(EntityFactory::createTome(1, bought: true));
        $comic->addTome(EntityFactory::createTome(2, bought: false));
        $comic->addTome(EntityFactory::createTome(3, bought: true));

        self::assertSame(3, $comic->getLastBought());
    }

    public function testGetLastDownloadedNoTomesReturnsNull(): void
    {
        $comic = EntityFactory::createComicSeries();

        self::assertNull($comic->getLastDownloaded());
    }

    public function testGetLastDownloadedNoneDownloadedReturnsNull(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->addTome(EntityFactory::createTome(1, downloaded: false));

        self::assertNull($comic->getLastDownloaded());
    }

    public function testGetLastDownloadedReturnsMaxDownloadedNumber(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->addTome(EntityFactory::createTome(1, downloaded: true));
        $comic->addTome(EntityFactory::createTome(2, downloaded: false));
        $comic->addTome(EntityFactory::createTome(4, downloaded: true));

        self::assertSame(4, $comic->getLastDownloaded());
    }

    public function testGetLastReadNoTomesReturnsNull(): void
    {
        $comic = EntityFactory::createComicSeries();

        self::assertNull($comic->getLastRead());
    }

    public function testGetLastReadNoneReadReturnsNull(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->addTome(EntityFactory::createTome(1, read: false));

        self::assertNull($comic->getLastRead());
    }

    public function testGetLastReadReturnsMaxReadNumber(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->addTome(EntityFactory::createTome(1, read: true));
        $comic->addTome(EntityFactory::createTome(3, read: true));
        $comic->addTome(EntityFactory::createTome(5, read: false));

        self::assertSame(3, $comic->getLastRead());
    }

    // ---------------------------------------------------------------
    // getMissingTomesNumbers
    // ---------------------------------------------------------------

    public function testGetMissingTomesNumbersNoLatestPublishedIssue(): void
    {
        $comic = EntityFactory::createComicSeries();

        self::assertSame([], $comic->getMissingTomesNumbers());
    }

    public function testGetMissingTomesNumbersLatestPublishedIssueZero(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->setLatestPublishedIssue(0);

        self::assertSame([], $comic->getMissingTomesNumbers());
    }

    public function testGetMissingTomesNumbersWithGaps(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->setLatestPublishedIssue(5);
        $comic->addTome(EntityFactory::createTome(1));
        $comic->addTome(EntityFactory::createTome(3));
        $comic->addTome(EntityFactory::createTome(5));

        self::assertSame([2, 4], $comic->getMissingTomesNumbers());
    }

    public function testGetMissingTomesNumbersAllOwned(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->setLatestPublishedIssue(3);
        $comic->addTome(EntityFactory::createTome(1));
        $comic->addTome(EntityFactory::createTome(2));
        $comic->addTome(EntityFactory::createTome(3));

        self::assertSame([], $comic->getMissingTomesNumbers());
    }

    public function testGetMissingTomesNumbersNoneOwned(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->setLatestPublishedIssue(3);

        self::assertSame([1, 2, 3], $comic->getMissingTomesNumbers());
    }

    // ---------------------------------------------------------------
    // getOwnedTomesNumbers
    // ---------------------------------------------------------------

    public function testGetOwnedTomesNumbersEmpty(): void
    {
        $comic = EntityFactory::createComicSeries();

        self::assertSame([], $comic->getOwnedTomesNumbers());
    }

    public function testGetOwnedTomesNumbersReturnsTomeNumbers(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->addTome(EntityFactory::createTome(2));
        $comic->addTome(EntityFactory::createTome(5));

        $numbers = $comic->getOwnedTomesNumbers();

        self::assertContains(2, $numbers);
        self::assertContains(5, $numbers);
        self::assertCount(2, $numbers);
    }

    // ---------------------------------------------------------------
    // getReadTomesCount
    // ---------------------------------------------------------------

    public function testGetReadTomesCountEmpty(): void
    {
        $comic = EntityFactory::createComicSeries();

        self::assertSame(0, $comic->getReadTomesCount());
    }

    public function testGetReadTomesCountCountsOnlyRead(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->addTome(EntityFactory::createTome(1, read: true));
        $comic->addTome(EntityFactory::createTome(2, read: false));
        $comic->addTome(EntityFactory::createTome(3, read: true));

        self::assertSame(2, $comic->getReadTomesCount());
    }

    // ---------------------------------------------------------------
    // isCurrentlyReading
    // ---------------------------------------------------------------

    public function testIsCurrentlyReadingEmptyReturnsFalse(): void
    {
        $comic = EntityFactory::createComicSeries();

        self::assertFalse($comic->isCurrentlyReading());
    }

    public function testIsCurrentlyReadingSomeReadReturnsTrue(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->addTome(EntityFactory::createTome(1, read: true));
        $comic->addTome(EntityFactory::createTome(2, read: false));

        self::assertTrue($comic->isCurrentlyReading());
    }

    public function testIsCurrentlyReadingAllReadReturnsFalse(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->addTome(EntityFactory::createTome(1, read: true));
        $comic->addTome(EntityFactory::createTome(2, read: true));

        self::assertFalse($comic->isCurrentlyReading());
    }

    public function testIsCurrentlyReadingNoneReadReturnsFalse(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->addTome(EntityFactory::createTome(1, read: false));
        $comic->addTome(EntityFactory::createTome(2, read: false));

        self::assertFalse($comic->isCurrentlyReading());
    }

    // ---------------------------------------------------------------
    // isCurrentIssueComplete / isLastBoughtComplete / etc.
    // ---------------------------------------------------------------

    public function testIsCurrentIssueCompleteNoTomesReturnsFalse(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->setLatestPublishedIssue(5);

        self::assertFalse($comic->isCurrentIssueComplete());
    }

    public function testIsCurrentIssueCompleteNoLatestPublishedReturnsFalse(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->addTome(EntityFactory::createTome(5));

        self::assertFalse($comic->isCurrentIssueComplete());
    }

    public function testIsCurrentIssueCompleteReturnsTrue(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->setLatestPublishedIssue(5);
        $comic->addTome(EntityFactory::createTome(5));

        self::assertTrue($comic->isCurrentIssueComplete());
    }

    public function testIsCurrentIssueCompleteExceedsLatestReturnsTrue(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->setLatestPublishedIssue(3);
        $comic->addTome(EntityFactory::createTome(5));

        self::assertTrue($comic->isCurrentIssueComplete());
    }

    public function testIsCurrentIssueCompleteReturnsFalseWhenBehind(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->setLatestPublishedIssue(10);
        $comic->addTome(EntityFactory::createTome(5));

        self::assertFalse($comic->isCurrentIssueComplete());
    }

    public function testIsLastBoughtCompleteReturnsTrue(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->setLatestPublishedIssue(3);
        $comic->addTome(EntityFactory::createTome(3, bought: true));

        self::assertTrue($comic->isLastBoughtComplete());
    }

    public function testIsLastBoughtCompleteReturnsFalseNoBought(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->setLatestPublishedIssue(3);
        $comic->addTome(EntityFactory::createTome(3, bought: false));

        self::assertFalse($comic->isLastBoughtComplete());
    }

    public function testIsLastDownloadedCompleteReturnsTrue(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->setLatestPublishedIssue(3);
        $comic->addTome(EntityFactory::createTome(3, downloaded: true));

        self::assertTrue($comic->isLastDownloadedComplete());
    }

    public function testIsLastDownloadedCompleteReturnsFalseNoDownloaded(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->setLatestPublishedIssue(3);
        $comic->addTome(EntityFactory::createTome(3, downloaded: false));

        self::assertFalse($comic->isLastDownloadedComplete());
    }

    public function testIsLastReadCompleteReturnsTrue(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->setLatestPublishedIssue(3);
        $comic->addTome(EntityFactory::createTome(3, read: true));

        self::assertTrue($comic->isLastReadComplete());
    }

    public function testIsLastReadCompleteReturnsFalseNoRead(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->setLatestPublishedIssue(3);
        $comic->addTome(EntityFactory::createTome(3, read: false));

        self::assertFalse($comic->isLastReadComplete());
    }

    // ---------------------------------------------------------------
    // isFullyRead
    // ---------------------------------------------------------------

    public function testIsFullyReadEmptyReturnsFalse(): void
    {
        $comic = EntityFactory::createComicSeries();

        self::assertFalse($comic->isFullyRead());
    }

    public function testIsFullyReadAllReadReturnsTrue(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->addTome(EntityFactory::createTome(1, read: true));
        $comic->addTome(EntityFactory::createTome(2, read: true));

        self::assertTrue($comic->isFullyRead());
    }

    public function testIsFullyReadSomeReadReturnsFalse(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->addTome(EntityFactory::createTome(1, read: true));
        $comic->addTome(EntityFactory::createTome(2, read: false));

        self::assertFalse($comic->isFullyRead());
    }

    public function testIsFullyReadNoneReadReturnsFalse(): void
    {
        $comic = EntityFactory::createComicSeries();
        $comic->addTome(EntityFactory::createTome(1, read: false));

        self::assertFalse($comic->isFullyRead());
    }

    // ---------------------------------------------------------------
    // isWishlist
    // ---------------------------------------------------------------

    public function testIsWishlistTrueWhenStatusIsWishlist(): void
    {
        $comic = EntityFactory::createComicSeries(status: ComicStatus::WISHLIST);

        self::assertTrue($comic->isWishlist());
    }

    public function testIsWishlistFalseWhenStatusIsBuying(): void
    {
        $comic = EntityFactory::createComicSeries(status: ComicStatus::BUYING);

        self::assertFalse($comic->isWishlist());
    }

    public function testIsWishlistFalseWhenStatusIsFinished(): void
    {
        $comic = EntityFactory::createComicSeries(status: ComicStatus::FINISHED);

        self::assertFalse($comic->isWishlist());
    }

    public function testIsWishlistFalseWhenStatusIsStopped(): void
    {
        $comic = EntityFactory::createComicSeries(status: ComicStatus::STOPPED);

        self::assertFalse($comic->isWishlist());
    }

    // ---------------------------------------------------------------
    // preUpdate
    // ---------------------------------------------------------------

    public function testPreUpdateSetsNewUpdatedAt(): void
    {
        $comic = new ComicSeries();
        $originalUpdatedAt = $comic->getUpdatedAt();

        \usleep(1000);

        $comic->preUpdate();

        self::assertInstanceOf(\DateTimeImmutable::class, $comic->getUpdatedAt());
        self::assertGreaterThan($originalUpdatedAt, $comic->getUpdatedAt());
    }
}
