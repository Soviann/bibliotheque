<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\ComicSeries;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
use App\Repository\ComicSeriesRepository;
use App\Tests\Factory\EntityFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests d'integration pour ComicSeriesRepository.
 */
final class ComicSeriesRepositoryTest extends KernelTestCase
{
    private ComicSeriesRepository $repository;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = static::getContainer()->get(ComicSeriesRepository::class);
    }

    // ---------------------------------------------------------------
    // findWithFilters
    // ---------------------------------------------------------------

    public function testFindWithFiltersNoFiltersReturnsAllNonDeletedOrderedByTitle(): void
    {
        $seriesC = EntityFactory::createComicSeries('Charlie');
        $seriesA = EntityFactory::createComicSeries('Alpha');
        $seriesB = EntityFactory::createComicSeries('Bravo');

        $this->em->persist($seriesA);
        $this->em->persist($seriesB);
        $this->em->persist($seriesC);
        $this->em->flush();

        $result = $this->repository->findWithFilters();

        self::assertCount(3, $result);
        self::assertSame('Alpha', $result[0]->getTitle());
        self::assertSame('Bravo', $result[1]->getTitle());
        self::assertSame('Charlie', $result[2]->getTitle());
    }

    public function testFindWithFiltersIsWishlistTrueReturnsOnlyWishlist(): void
    {
        $wishlist = EntityFactory::createComicSeries('Wish', ComicStatus::WISHLIST);
        $buying = EntityFactory::createComicSeries('Buy', ComicStatus::BUYING);

        $this->em->persist($buying);
        $this->em->persist($wishlist);
        $this->em->flush();

        $result = $this->repository->findWithFilters(['isWishlist' => true]);

        self::assertCount(1, $result);
        self::assertSame('Wish', $result[0]->getTitle());
    }

    public function testFindWithFiltersIsWishlistFalseExcludesWishlist(): void
    {
        $wishlist = EntityFactory::createComicSeries('Wish', ComicStatus::WISHLIST);
        $buying = EntityFactory::createComicSeries('Buy', ComicStatus::BUYING);
        $finished = EntityFactory::createComicSeries('Fin', ComicStatus::FINISHED);

        $this->em->persist($buying);
        $this->em->persist($finished);
        $this->em->persist($wishlist);
        $this->em->flush();

        $result = $this->repository->findWithFilters(['isWishlist' => false]);

        self::assertCount(2, $result);
        $titles = \array_map(static fn (ComicSeries $s): string => $s->getTitle(), $result);
        self::assertContains('Buy', $titles);
        self::assertContains('Fin', $titles);
        self::assertNotContains('Wish', $titles);
    }

    public function testFindWithFiltersTypeFilter(): void
    {
        $manga = EntityFactory::createComicSeries('Naruto', ComicStatus::BUYING, ComicType::MANGA);
        $bd = EntityFactory::createComicSeries('Asterix', ComicStatus::BUYING, ComicType::BD);

        $this->em->persist($bd);
        $this->em->persist($manga);
        $this->em->flush();

        $result = $this->repository->findWithFilters(['type' => ComicType::MANGA]);

        self::assertCount(1, $result);
        self::assertSame('Naruto', $result[0]->getTitle());
    }

    public function testFindWithFiltersStatusFilter(): void
    {
        $buying = EntityFactory::createComicSeries('Buy', ComicStatus::BUYING);
        $finished = EntityFactory::createComicSeries('Fin', ComicStatus::FINISHED);
        $stopped = EntityFactory::createComicSeries('Stop', ComicStatus::STOPPED);

        $this->em->persist($buying);
        $this->em->persist($finished);
        $this->em->persist($stopped);
        $this->em->flush();

        $result = $this->repository->findWithFilters(['status' => ComicStatus::FINISHED]);

        self::assertCount(1, $result);
        self::assertSame('Fin', $result[0]->getTitle());
    }

    public function testFindWithFiltersSearchByTitle(): void
    {
        $asterix = EntityFactory::createComicSeries('Asterix et Obelix');
        $naruto = EntityFactory::createComicSeries('Naruto');

        $this->em->persist($asterix);
        $this->em->persist($naruto);
        $this->em->flush();

        $result = $this->repository->findWithFilters(['search' => 'Aster']);

        self::assertCount(1, $result);
        self::assertSame('Asterix et Obelix', $result[0]->getTitle());
    }

    public function testFindWithFiltersSearchByIsbn(): void
    {
        $series = EntityFactory::createComicSeries('Serie A');
        $tome = EntityFactory::createTome(1);
        $tome->setIsbn('978-2-1234-5678-9');
        $series->addTome($tome);

        $other = EntityFactory::createComicSeries('Serie B');

        $this->em->persist($other);
        $this->em->persist($series);
        $this->em->flush();

        $result = $this->repository->findWithFilters(['search' => '978-2-1234']);

        self::assertCount(1, $result);
        self::assertSame('Serie A', $result[0]->getTitle());
    }

    public function testFindWithFiltersOnNasTrueReturnsSeriesWithTomeOnNas(): void
    {
        $withNas = EntityFactory::createComicSeries('With NAS');
        $tomeNas = EntityFactory::createTome(1, onNas: true);
        $withNas->addTome($tomeNas);

        $withoutNas = EntityFactory::createComicSeries('Without NAS');
        $tomeNoNas = EntityFactory::createTome(1, onNas: false);
        $withoutNas->addTome($tomeNoNas);

        $this->em->persist($withNas);
        $this->em->persist($withoutNas);
        $this->em->flush();

        $result = $this->repository->findWithFilters(['onNas' => true]);

        self::assertCount(1, $result);
        self::assertSame('With NAS', $result[0]->getTitle());
    }

    public function testFindWithFiltersOnNasFalseReturnsSeriesWithoutAnyTomeOnNas(): void
    {
        $withNas = EntityFactory::createComicSeries('With NAS');
        $tomeNas = EntityFactory::createTome(1, onNas: true);
        $withNas->addTome($tomeNas);

        $withoutNas = EntityFactory::createComicSeries('Without NAS');
        $tomeNoNas = EntityFactory::createTome(1, onNas: false);
        $withoutNas->addTome($tomeNoNas);

        $this->em->persist($withNas);
        $this->em->persist($withoutNas);
        $this->em->flush();

        $result = $this->repository->findWithFilters(['onNas' => false]);

        self::assertCount(1, $result);
        self::assertSame('Without NAS', $result[0]->getTitle());
    }

    public function testFindWithFiltersReadingReturnsPartiallyRead(): void
    {
        // Serie partiellement lue : tome 1 lu, tome 2 non lu
        $partial = EntityFactory::createComicSeries('Partial');
        $partial->addTome(EntityFactory::createTome(1, read: true));
        $partial->addTome(EntityFactory::createTome(2, read: false));

        // Serie entierement lue
        $fullyRead = EntityFactory::createComicSeries('Full');
        $fullyRead->addTome(EntityFactory::createTome(1, read: true));

        // Serie non lue
        $unread = EntityFactory::createComicSeries('Unread');
        $unread->addTome(EntityFactory::createTome(1, read: false));

        $this->em->persist($fullyRead);
        $this->em->persist($partial);
        $this->em->persist($unread);
        $this->em->flush();

        $result = $this->repository->findWithFilters(['reading' => 'reading']);

        self::assertCount(1, $result);
        self::assertSame('Partial', $result[0]->getTitle());
    }

    public function testFindWithFiltersReadReturnsFullyRead(): void
    {
        $fullyRead = EntityFactory::createComicSeries('Full');
        $fullyRead->addTome(EntityFactory::createTome(1, read: true));
        $fullyRead->addTome(EntityFactory::createTome(2, read: true));

        $partial = EntityFactory::createComicSeries('Partial');
        $partial->addTome(EntityFactory::createTome(1, read: true));
        $partial->addTome(EntityFactory::createTome(2, read: false));

        $this->em->persist($fullyRead);
        $this->em->persist($partial);
        $this->em->flush();

        $result = $this->repository->findWithFilters(['reading' => 'read']);

        self::assertCount(1, $result);
        self::assertSame('Full', $result[0]->getTitle());
    }

    public function testFindWithFiltersUnreadReturnsNoTomesRead(): void
    {
        $unread = EntityFactory::createComicSeries('Unread');
        $unread->addTome(EntityFactory::createTome(1, read: false));
        $unread->addTome(EntityFactory::createTome(2, read: false));

        $partial = EntityFactory::createComicSeries('Partial');
        $partial->addTome(EntityFactory::createTome(1, read: true));
        $partial->addTome(EntityFactory::createTome(2, read: false));

        $this->em->persist($partial);
        $this->em->persist($unread);
        $this->em->flush();

        $result = $this->repository->findWithFilters(['reading' => 'unread']);

        self::assertCount(1, $result);
        self::assertSame('Unread', $result[0]->getTitle());
    }

    public function testFindWithFiltersSortByTitleDesc(): void
    {
        $this->em->persist(EntityFactory::createComicSeries('Alpha'));
        $this->em->persist(EntityFactory::createComicSeries('Charlie'));
        $this->em->persist(EntityFactory::createComicSeries('Bravo'));
        $this->em->flush();

        $result = $this->repository->findWithFilters(['sort' => 'title_desc']);

        self::assertCount(3, $result);
        self::assertSame('Charlie', $result[0]->getTitle());
        self::assertSame('Bravo', $result[1]->getTitle());
        self::assertSame('Alpha', $result[2]->getTitle());
    }

    public function testFindWithFiltersSortByUpdatedDesc(): void
    {
        $old = EntityFactory::createComicSeries('Old');
        $old->setUpdatedAt(new \DateTimeImmutable('2024-01-01'));
        $recent = EntityFactory::createComicSeries('Recent');
        $recent->setUpdatedAt(new \DateTimeImmutable('2025-06-01'));
        $mid = EntityFactory::createComicSeries('Mid');
        $mid->setUpdatedAt(new \DateTimeImmutable('2024-06-01'));

        $this->em->persist($mid);
        $this->em->persist($old);
        $this->em->persist($recent);
        $this->em->flush();

        $result = $this->repository->findWithFilters(['sort' => 'updated_desc']);

        self::assertCount(3, $result);
        self::assertSame('Recent', $result[0]->getTitle());
        self::assertSame('Mid', $result[1]->getTitle());
        self::assertSame('Old', $result[2]->getTitle());
    }

    public function testFindWithFiltersSortByStatus(): void
    {
        $stopped = EntityFactory::createComicSeries('Stopped', ComicStatus::STOPPED);
        $buying = EntityFactory::createComicSeries('Buying', ComicStatus::BUYING);
        $finished = EntityFactory::createComicSeries('Finished', ComicStatus::FINISHED);

        $this->em->persist($buying);
        $this->em->persist($finished);
        $this->em->persist($stopped);
        $this->em->flush();

        $result = $this->repository->findWithFilters(['sort' => 'status']);

        // Tri par status ASC puis title ASC
        self::assertCount(3, $result);
        // Les statuts sont triés par valeur string : buying < finished < stopped
        self::assertSame(ComicStatus::BUYING, $result[0]->getStatus());
        self::assertSame(ComicStatus::FINISHED, $result[1]->getStatus());
        self::assertSame(ComicStatus::STOPPED, $result[2]->getStatus());
    }

    public function testFindWithFiltersCombinedFilters(): void
    {
        $match = EntityFactory::createComicSeries('Naruto', ComicStatus::BUYING, ComicType::MANGA);
        $match->addTome(EntityFactory::createTome(1, read: true));
        $match->addTome(EntityFactory::createTome(2, read: false));

        $wrongType = EntityFactory::createComicSeries('Asterix', ComicStatus::BUYING, ComicType::BD);
        $wrongType->addTome(EntityFactory::createTome(1, read: true));
        $wrongType->addTome(EntityFactory::createTome(2, read: false));

        $wrongReading = EntityFactory::createComicSeries('One Piece', ComicStatus::BUYING, ComicType::MANGA);
        $wrongReading->addTome(EntityFactory::createTome(1, read: true));

        $this->em->persist($match);
        $this->em->persist($wrongReading);
        $this->em->persist($wrongType);
        $this->em->flush();

        $result = $this->repository->findWithFilters([
            'reading' => 'reading',
            'type' => ComicType::MANGA,
        ]);

        self::assertCount(1, $result);
        self::assertSame('Naruto', $result[0]->getTitle());
    }

    public function testFindWithFiltersSoftDeletedSeriesExcluded(): void
    {
        $active = EntityFactory::createComicSeries('Active');
        $deleted = EntityFactory::createComicSeries('Deleted');
        $deleted->delete();

        $this->em->persist($active);
        $this->em->persist($deleted);
        $this->em->flush();

        $result = $this->repository->findWithFilters();

        self::assertCount(1, $result);
        self::assertSame('Active', $result[0]->getTitle());
    }

    // ---------------------------------------------------------------
    // findAllForApi
    // ---------------------------------------------------------------

    public function testFindAllForApiReturnsCorrectStructure(): void
    {
        $author = EntityFactory::createAuthor('Goscinny');
        $this->em->persist($author);

        $series = EntityFactory::createComicSeries('Asterix', ComicStatus::BUYING, ComicType::BD);
        $series->setLatestPublishedIssue(5);
        $series->setLatestPublishedIssueComplete(true);
        $series->setDescription('Les aventures d\'Asterix');
        $series->setPublisher('Hachette');
        $series->setPublishedDate('1959');
        $series->setCoverUrl('https://example.com/cover.jpg');
        $series->addAuthor($author);

        $tome1 = EntityFactory::createTome(1, bought: true, read: true, onNas: true);
        $tome2 = EntityFactory::createTome(2, bought: true, read: false);
        $series->addTome($tome1);
        $series->addTome($tome2);

        $this->em->persist($series);
        $this->em->flush();

        $result = $this->repository->findAllForApi();

        self::assertCount(1, $result);

        $item = $result[0];

        // Verification des cles attendues
        $expectedKeys = [
            'authors',
            'coverUrl',
            'currentIssue',
            'currentIssueComplete',
            'description',
            'hasNasTome',
            'id',
            'isCurrentlyReading',
            'isFullyRead',
            'isOneShot',
            'isWishlist',
            'lastBought',
            'lastBoughtComplete',
            'lastDownloaded',
            'lastDownloadedComplete',
            'lastRead',
            'lastReadComplete',
            'latestPublishedIssue',
            'latestPublishedIssueComplete',
            'missingTomesNumbers',
            'ownedTomesNumbers',
            'publishedDate',
            'publisher',
            'readTomesCount',
            'status',
            'statusLabel',
            'title',
            'tomesCount',
            'type',
            'typeLabel',
            'updatedAt',
        ];
        foreach ($expectedKeys as $key) {
            self::assertArrayHasKey($key, $item, \sprintf('Cle manquante : %s', $key));
        }

        // Verification des valeurs
        self::assertSame('Goscinny', $item['authors']);
        self::assertSame('Asterix', $item['title']);
        self::assertSame('buying', $item['status']);
        self::assertSame('En cours d\'achat', $item['statusLabel']);
        self::assertSame('bd', $item['type']);
        self::assertSame('BD', $item['typeLabel']);
        self::assertSame(2, $item['currentIssue']);
        self::assertSame(5, $item['latestPublishedIssue']);
        self::assertTrue($item['latestPublishedIssueComplete']);
        self::assertSame('Les aventures d\'Asterix', $item['description']);
        self::assertSame('Hachette', $item['publisher']);
        self::assertSame('1959', $item['publishedDate']);
        self::assertSame('https://example.com/cover.jpg', $item['coverUrl']);
        self::assertTrue($item['hasNasTome']);
        self::assertTrue($item['isCurrentlyReading']);
        self::assertFalse($item['isFullyRead']);
        self::assertFalse($item['isWishlist']);
        self::assertFalse($item['isOneShot']);
        self::assertSame(2, $item['tomesCount']);
        self::assertSame(1, $item['readTomesCount']);
        self::assertSame(1, $item['lastRead']);
        self::assertSame(2, $item['lastBought']);
        self::assertContains(3, $item['missingTomesNumbers']);
        self::assertContains(4, $item['missingTomesNumbers']);
        self::assertContains(5, $item['missingTomesNumbers']);
        self::assertContains(1, $item['ownedTomesNumbers']);
        self::assertContains(2, $item['ownedTomesNumbers']);
    }
}
