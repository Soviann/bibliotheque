<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Author;
use App\Entity\ComicSeries;
use App\Entity\Tome;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Tests unitaires pour l'entité ComicSeries.
 */
class ComicSeriesTest extends TestCase
{
    /**
     * Teste que le constructeur initialise les collections et timestamps.
     */
    public function testConstructorInitializesCollectionsAndTimestamps(): void
    {
        $before = new \DateTimeImmutable();
        $series = new ComicSeries();
        $after = new \DateTimeImmutable();

        self::assertCount(0, $series->getAuthors());
        self::assertCount(0, $series->getTomes());
        self::assertGreaterThanOrEqual($before, $series->getCreatedAt());
        self::assertLessThanOrEqual($after, $series->getCreatedAt());
        self::assertGreaterThanOrEqual($before, $series->getUpdatedAt());
        self::assertLessThanOrEqual($after, $series->getUpdatedAt());
    }

    /**
     * Teste les valeurs par défaut des enums.
     */
    public function testDefaultEnumValues(): void
    {
        $series = new ComicSeries();

        self::assertSame(ComicStatus::BUYING, $series->getStatus());
        self::assertSame(ComicType::BD, $series->getType());
    }

    /**
     * Teste les valeurs par défaut des booléens.
     */
    public function testDefaultBooleanValues(): void
    {
        $series = new ComicSeries();

        self::assertFalse($series->isOneShot());
        self::assertFalse($series->isWishlist());
        self::assertFalse($series->isLatestPublishedIssueComplete());
    }

    /**
     * Teste que isWishlist retourne true quand le statut est WISHLIST.
     */
    public function testIsWishlistReturnsTrueWhenStatusIsWishlist(): void
    {
        $series = new ComicSeries();
        $series->setStatus(ComicStatus::WISHLIST);

        self::assertTrue($series->isWishlist());
    }

    /**
     * Teste que isWishlist retourne false pour les autres statuts.
     */
    public function testIsWishlistReturnsFalseForOtherStatuses(): void
    {
        $series = new ComicSeries();

        $series->setStatus(ComicStatus::BUYING);
        self::assertFalse($series->isWishlist());

        $series->setStatus(ComicStatus::FINISHED);
        self::assertFalse($series->isWishlist());

        $series->setStatus(ComicStatus::STOPPED);
        self::assertFalse($series->isWishlist());
    }

    /**
     * Teste getCurrentIssue avec une collection vide.
     */
    public function testGetCurrentIssueWithEmptyCollection(): void
    {
        $series = new ComicSeries();

        self::assertNull($series->getCurrentIssue());
    }

    /**
     * Teste getCurrentIssue retourne le numéro maximum des tomes.
     */
    public function testGetCurrentIssueReturnsMaxNumber(): void
    {
        $series = new ComicSeries();

        $tome1 = new Tome();
        $tome1->setNumber(1);
        $series->addTome($tome1);

        $tome2 = new Tome();
        $tome2->setNumber(5);
        $series->addTome($tome2);

        $tome3 = new Tome();
        $tome3->setNumber(3);
        $series->addTome($tome3);

        self::assertSame(5, $series->getCurrentIssue());
    }

    /**
     * Teste getLastBought avec aucun tome acheté.
     */
    public function testGetLastBoughtWithNoBoughtTomes(): void
    {
        $series = new ComicSeries();

        $tome = new Tome();
        $tome->setNumber(1);
        $tome->setBought(false);
        $series->addTome($tome);

        self::assertNull($series->getLastBought());
    }

    /**
     * Teste getLastBought retourne le numéro maximum des tomes achetés.
     */
    public function testGetLastBoughtReturnsMaxBoughtNumber(): void
    {
        $series = new ComicSeries();

        $tome1 = new Tome();
        $tome1->setNumber(1);
        $tome1->setBought(true);
        $series->addTome($tome1);

        $tome2 = new Tome();
        $tome2->setNumber(3);
        $tome2->setBought(true);
        $series->addTome($tome2);

        $tome3 = new Tome();
        $tome3->setNumber(5);
        $tome3->setBought(false);
        $series->addTome($tome3);

        self::assertSame(3, $series->getLastBought());
    }

    /**
     * Teste getLastDownloaded avec aucun tome téléchargé.
     */
    public function testGetLastDownloadedWithNoDownloadedTomes(): void
    {
        $series = new ComicSeries();

        $tome = new Tome();
        $tome->setNumber(1);
        $tome->setDownloaded(false);
        $series->addTome($tome);

        self::assertNull($series->getLastDownloaded());
    }

    /**
     * Teste getLastDownloaded retourne le numéro maximum des tomes téléchargés.
     */
    public function testGetLastDownloadedReturnsMaxDownloadedNumber(): void
    {
        $series = new ComicSeries();

        $tome1 = new Tome();
        $tome1->setNumber(1);
        $tome1->setDownloaded(true);
        $series->addTome($tome1);

        $tome2 = new Tome();
        $tome2->setNumber(4);
        $tome2->setDownloaded(true);
        $series->addTome($tome2);

        $tome3 = new Tome();
        $tome3->setNumber(6);
        $tome3->setDownloaded(false);
        $series->addTome($tome3);

        self::assertSame(4, $series->getLastDownloaded());
    }

    /**
     * Teste getOwnedTomesNumbers retourne les numéros des tomes.
     */
    public function testGetOwnedTomesNumbers(): void
    {
        $series = new ComicSeries();

        $tome1 = new Tome();
        $tome1->setNumber(1);
        $series->addTome($tome1);

        $tome2 = new Tome();
        $tome2->setNumber(3);
        $series->addTome($tome2);

        $tome3 = new Tome();
        $tome3->setNumber(5);
        $series->addTome($tome3);

        $numbers = $series->getOwnedTomesNumbers();

        self::assertContains(1, $numbers);
        self::assertContains(3, $numbers);
        self::assertContains(5, $numbers);
        self::assertCount(3, $numbers);
    }

    /**
     * Teste getMissingTomesNumbers avec latestPublishedIssue null.
     */
    public function testGetMissingTomesNumbersWithNullLatestPublished(): void
    {
        $series = new ComicSeries();

        self::assertSame([], $series->getMissingTomesNumbers());
    }

    /**
     * Teste getMissingTomesNumbers avec latestPublishedIssue à 0.
     */
    public function testGetMissingTomesNumbersWithZeroLatestPublished(): void
    {
        $series = new ComicSeries();
        $series->setLatestPublishedIssue(0);

        self::assertSame([], $series->getMissingTomesNumbers());
    }

    /**
     * Teste getMissingTomesNumbers retourne les tomes manquants.
     */
    public function testGetMissingTomesNumbers(): void
    {
        $series = new ComicSeries();
        $series->setLatestPublishedIssue(5);

        $tome1 = new Tome();
        $tome1->setNumber(1);
        $series->addTome($tome1);

        $tome2 = new Tome();
        $tome2->setNumber(3);
        $series->addTome($tome2);

        $missing = $series->getMissingTomesNumbers();

        self::assertSame([2, 4, 5], $missing);
    }

    /**
     * Teste getMissingTomesNumbers quand tous les tomes sont possédés.
     */
    public function testGetMissingTomesNumbersWhenComplete(): void
    {
        $series = new ComicSeries();
        $series->setLatestPublishedIssue(3);

        for ($i = 1; $i <= 3; ++$i) {
            $tome = new Tome();
            $tome->setNumber($i);
            $series->addTome($tome);
        }

        self::assertSame([], $series->getMissingTomesNumbers());
    }

    /**
     * Teste isCurrentIssueComplete quand complet.
     */
    public function testIsCurrentIssueCompleteWhenComplete(): void
    {
        $series = new ComicSeries();
        $series->setLatestPublishedIssue(3);

        $tome = new Tome();
        $tome->setNumber(3);
        $series->addTome($tome);

        self::assertTrue($series->isCurrentIssueComplete());
    }

    /**
     * Teste isCurrentIssueComplete quand incomplet.
     */
    public function testIsCurrentIssueCompleteWhenIncomplete(): void
    {
        $series = new ComicSeries();
        $series->setLatestPublishedIssue(5);

        $tome = new Tome();
        $tome->setNumber(3);
        $series->addTome($tome);

        self::assertFalse($series->isCurrentIssueComplete());
    }

    /**
     * Teste isCurrentIssueComplete quand latestPublishedIssue est null.
     */
    public function testIsCurrentIssueCompleteWithNullLatestPublished(): void
    {
        $series = new ComicSeries();

        $tome = new Tome();
        $tome->setNumber(3);
        $series->addTome($tome);

        self::assertFalse($series->isCurrentIssueComplete());
    }

    /**
     * Teste isLastBoughtComplete quand complet.
     */
    public function testIsLastBoughtCompleteWhenComplete(): void
    {
        $series = new ComicSeries();
        $series->setLatestPublishedIssue(3);

        $tome = new Tome();
        $tome->setNumber(3);
        $tome->setBought(true);
        $series->addTome($tome);

        self::assertTrue($series->isLastBoughtComplete());
    }

    /**
     * Teste isLastBoughtComplete quand incomplet.
     */
    public function testIsLastBoughtCompleteWhenIncomplete(): void
    {
        $series = new ComicSeries();
        $series->setLatestPublishedIssue(5);

        $tome = new Tome();
        $tome->setNumber(3);
        $tome->setBought(true);
        $series->addTome($tome);

        self::assertFalse($series->isLastBoughtComplete());
    }

    /**
     * Teste isLastDownloadedComplete quand complet.
     */
    public function testIsLastDownloadedCompleteWhenComplete(): void
    {
        $series = new ComicSeries();
        $series->setLatestPublishedIssue(3);

        $tome = new Tome();
        $tome->setNumber(3);
        $tome->setDownloaded(true);
        $series->addTome($tome);

        self::assertTrue($series->isLastDownloadedComplete());
    }

    /**
     * Teste isLastDownloadedComplete quand incomplet.
     */
    public function testIsLastDownloadedCompleteWhenIncomplete(): void
    {
        $series = new ComicSeries();
        $series->setLatestPublishedIssue(5);

        $tome = new Tome();
        $tome->setNumber(3);
        $tome->setDownloaded(true);
        $series->addTome($tome);

        self::assertFalse($series->isLastDownloadedComplete());
    }

    /**
     * Teste getAuthorsAsString avec aucun auteur.
     */
    public function testGetAuthorsAsStringWithNoAuthors(): void
    {
        $series = new ComicSeries();

        self::assertSame('', $series->getAuthorsAsString());
    }

    /**
     * Teste getAuthorsAsString avec un seul auteur.
     */
    public function testGetAuthorsAsStringWithOneAuthor(): void
    {
        $series = new ComicSeries();
        $author = new Author();
        $author->setName('Jean Dupont');
        $series->addAuthor($author);

        self::assertSame('Jean Dupont', $series->getAuthorsAsString());
    }

    /**
     * Teste getAuthorsAsString avec plusieurs auteurs.
     */
    public function testGetAuthorsAsStringWithMultipleAuthors(): void
    {
        $series = new ComicSeries();

        $author1 = new Author();
        $author1->setName('Jean Dupont');
        $series->addAuthor($author1);

        $author2 = new Author();
        $author2->setName('Marie Martin');
        $series->addAuthor($author2);

        $result = $series->getAuthorsAsString();

        self::assertStringContainsString('Jean Dupont', $result);
        self::assertStringContainsString('Marie Martin', $result);
        self::assertStringContainsString(', ', $result);
    }

    /**
     * Teste addAuthor.
     */
    public function testAddAuthor(): void
    {
        $series = new ComicSeries();
        $author = new Author();

        $series->addAuthor($author);

        self::assertCount(1, $series->getAuthors());
        self::assertTrue($series->getAuthors()->contains($author));
    }

    /**
     * Teste addAuthor ne duplique pas.
     */
    public function testAddAuthorDoesNotDuplicate(): void
    {
        $series = new ComicSeries();
        $author = new Author();

        $series->addAuthor($author);
        $series->addAuthor($author);

        self::assertCount(1, $series->getAuthors());
    }

    /**
     * Teste removeAuthor.
     */
    public function testRemoveAuthor(): void
    {
        $series = new ComicSeries();
        $author = new Author();

        $series->addAuthor($author);
        $series->removeAuthor($author);

        self::assertCount(0, $series->getAuthors());
    }

    /**
     * Teste addTome est bidirectionnel.
     */
    public function testAddTomeIsBidirectional(): void
    {
        $series = new ComicSeries();
        $tome = new Tome();

        $series->addTome($tome);

        self::assertSame($series, $tome->getComicSeries());
    }

    /**
     * Teste addTome ne duplique pas.
     */
    public function testAddTomeDoesNotDuplicate(): void
    {
        $series = new ComicSeries();
        $tome = new Tome();

        $series->addTome($tome);
        $series->addTome($tome);

        self::assertCount(1, $series->getTomes());
    }

    /**
     * Teste removeTome est bidirectionnel.
     */
    public function testRemoveTomeIsBidirectional(): void
    {
        $series = new ComicSeries();
        $tome = new Tome();

        $series->addTome($tome);
        $series->removeTome($tome);

        self::assertNull($tome->getComicSeries());
    }

    /**
     * Teste que setCoverFile met à jour updatedAt.
     */
    public function testSetCoverFileUpdatesUpdatedAt(): void
    {
        $series = new ComicSeries();
        $oldUpdatedAt = $series->getUpdatedAt();

        // Attendre une microseconde pour avoir une différence
        \usleep(1000);

        // Créer un mock de File
        $file = $this->createMock(File::class);
        $series->setCoverFile($file);

        self::assertGreaterThan($oldUpdatedAt, $series->getUpdatedAt());
    }

    /**
     * Teste que setCoverFile avec null ne met pas à jour updatedAt.
     */
    public function testSetCoverFileWithNullDoesNotUpdateUpdatedAt(): void
    {
        $series = new ComicSeries();
        $oldUpdatedAt = $series->getUpdatedAt();

        $series->setCoverFile();

        self::assertEquals($oldUpdatedAt, $series->getUpdatedAt());
    }

    /**
     * Teste les getters/setters simples.
     */
    public function testSimpleGettersAndSetters(): void
    {
        $series = new ComicSeries();

        $series->setTitle('Test Title');
        self::assertSame('Test Title', $series->getTitle());

        $series->setStatus(ComicStatus::FINISHED);
        self::assertSame(ComicStatus::FINISHED, $series->getStatus());

        $series->setType(ComicType::MANGA);
        self::assertSame(ComicType::MANGA, $series->getType());

        $series->setIsOneShot(true);
        self::assertTrue($series->isOneShot());

        // isWishlist est maintenant calculé à partir du statut
        $series->setStatus(ComicStatus::WISHLIST);
        self::assertTrue($series->isWishlist());

        $series->setLatestPublishedIssue(10);
        self::assertSame(10, $series->getLatestPublishedIssue());

        $series->setLatestPublishedIssueComplete(true);
        self::assertTrue($series->isLatestPublishedIssueComplete());

        $series->setDescription('Une description');
        self::assertSame('Une description', $series->getDescription());

        $series->setPublishedDate('2023-01-15');
        self::assertSame('2023-01-15', $series->getPublishedDate());

        $series->setPublisher('Dupuis');
        self::assertSame('Dupuis', $series->getPublisher());

        $series->setCoverImage('cover.jpg');
        self::assertSame('cover.jpg', $series->getCoverImage());

        $series->setCoverUrl('https://example.com/cover.jpg');
        self::assertSame('https://example.com/cover.jpg', $series->getCoverUrl());
    }

    /**
     * Teste que getId retourne null pour une nouvelle entité.
     */
    public function testGetIdReturnsNullForNewEntity(): void
    {
        $series = new ComicSeries();

        self::assertNull($series->getId());
    }

    /**
     * Teste que les setters retournent l'instance pour le chaînage.
     */
    public function testSettersReturnInstanceForChaining(): void
    {
        $series = new ComicSeries();

        self::assertSame($series, $series->setTitle('Test'));
        self::assertSame($series, $series->setStatus(ComicStatus::BUYING));
        self::assertSame($series, $series->setType(ComicType::BD));
        self::assertSame($series, $series->setIsOneShot(true));
        // setIsWishlist supprimé : isWishlist est calculé à partir du statut
        self::assertSame($series, $series->setLatestPublishedIssue(5));
        self::assertSame($series, $series->setLatestPublishedIssueComplete(true));
        self::assertSame($series, $series->setDescription('Test'));
        self::assertSame($series, $series->setPublishedDate('2023'));
        self::assertSame($series, $series->setPublisher('Test'));
        self::assertSame($series, $series->setCoverImage('test.jpg'));
        self::assertSame($series, $series->setCoverUrl('https://test.com'));
        self::assertSame($series, $series->addAuthor(new Author()));
        self::assertSame($series, $series->removeAuthor(new Author()));
        self::assertSame($series, $series->addTome(new Tome()));
    }

    /**
     * Teste getCurrentIssue avec des tomes ayant des numéros null.
     */
    public function testGetCurrentIssueIgnoresNullNumbers(): void
    {
        $series = new ComicSeries();

        $tome1 = new Tome();
        $tome1->setNumber(3);
        $series->addTome($tome1);

        // Créer un tome sans numéro défini (null par défaut)
        $tome2 = new Tome();
        $series->addTome($tome2);

        self::assertSame(3, $series->getCurrentIssue());
    }

    /**
     * Teste isCurrentIssueComplete quand le current dépasse le latest.
     */
    public function testIsCurrentIssueCompleteWhenExceedsLatest(): void
    {
        $series = new ComicSeries();
        $series->setLatestPublishedIssue(3);

        $tome = new Tome();
        $tome->setNumber(5);
        $series->addTome($tome);

        self::assertTrue($series->isCurrentIssueComplete());
    }
}
