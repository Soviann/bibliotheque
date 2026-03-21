<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\DTO\ComicSeriesFilter;
use App\DTO\ComicSeriesListItem;
use App\Entity\ComicSeries;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
use App\Repository\ComicSeriesRepository;
use App\Tests\Factory\EntityFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Tests d'integration pour ComicSeriesRepository.
 */
final class ComicSeriesRepositoryTest extends KernelTestCase
{
    private CacheInterface $cache;
    private ComicSeriesRepository $repository;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->cache = self::getContainer()->get('comic_series_api.cache');
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(ComicSeriesRepository::class);

        // Vider le cache avant chaque test pour garantir l'isolation
        $this->cache->delete('comic_series_api_all');
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

        $result = $this->repository->findWithFilters(new ComicSeriesFilter(isWishlist: true));

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

        $result = $this->repository->findWithFilters(new ComicSeriesFilter(isWishlist: false));

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

        $result = $this->repository->findWithFilters(new ComicSeriesFilter(type: ComicType::MANGA));

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

        $result = $this->repository->findWithFilters(new ComicSeriesFilter(status: ComicStatus::FINISHED));

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

        $result = $this->repository->findWithFilters(new ComicSeriesFilter(search: 'Aster'));

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

        $result = $this->repository->findWithFilters(new ComicSeriesFilter(search: '978-2-1234'));

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

        $result = $this->repository->findWithFilters(new ComicSeriesFilter(onNas: true));

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

        $result = $this->repository->findWithFilters(new ComicSeriesFilter(onNas: false));

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

        $result = $this->repository->findWithFilters(new ComicSeriesFilter(reading: 'reading'));

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

        $result = $this->repository->findWithFilters(new ComicSeriesFilter(reading: 'read'));

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

        $result = $this->repository->findWithFilters(new ComicSeriesFilter(reading: 'unread'));

        self::assertCount(1, $result);
        self::assertSame('Unread', $result[0]->getTitle());
    }

    public function testFindWithFiltersSortByTitleDesc(): void
    {
        $this->em->persist(EntityFactory::createComicSeries('Alpha'));
        $this->em->persist(EntityFactory::createComicSeries('Charlie'));
        $this->em->persist(EntityFactory::createComicSeries('Bravo'));
        $this->em->flush();

        $result = $this->repository->findWithFilters(new ComicSeriesFilter(sort: 'title_desc'));

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

        $result = $this->repository->findWithFilters(new ComicSeriesFilter(sort: 'updated_desc'));

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

        $result = $this->repository->findWithFilters(new ComicSeriesFilter(sort: 'status'));

        // Tri par status ASC puis title ASC
        self::assertCount(3, $result);
        // Les statuts sont triés par valeur string : buying < finished < stopped
        self::assertSame(ComicStatus::BUYING, $result[0]->getStatus());
        self::assertSame(ComicStatus::FINISHED, $result[1]->getStatus());
        self::assertSame(ComicStatus::STOPPED, $result[2]->getStatus());
    }

    public function testFindWithFiltersSortByUpdatedAsc(): void
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

        $result = $this->repository->findWithFilters(new ComicSeriesFilter(sort: 'updated_asc'));

        self::assertCount(3, $result);
        self::assertSame('Old', $result[0]->getTitle());
        self::assertSame('Mid', $result[1]->getTitle());
        self::assertSame('Recent', $result[2]->getTitle());
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

        $result = $this->repository->findWithFilters(new ComicSeriesFilter(reading: 'reading', type: ComicType::MANGA));

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

        $tome1 = EntityFactory::createTome(1, bought: true, onNas: true, read: true);
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
        self::assertInstanceOf(ComicSeriesListItem::class, $item);

        // Vérification que jsonSerialize contient toutes les clés attendues
        $serialized = $item->jsonSerialize();
        foreach ($expectedKeys as $key) {
            self::assertArrayHasKey($key, $serialized, \sprintf('Cle manquante : %s', $key));
        }

        // Verification des valeurs
        self::assertSame('Goscinny', $item->authors);
        self::assertSame('Asterix', $item->title);
        self::assertSame('buying', $item->status);
        self::assertSame('En cours d\'achat', $item->statusLabel);
        self::assertSame('bd', $item->type);
        self::assertSame('BD', $item->typeLabel);
        self::assertSame(2, $item->currentIssue);
        self::assertSame(5, $item->latestPublishedIssue);
        self::assertTrue($item->latestPublishedIssueComplete);
        self::assertSame('Les aventures d\'Asterix', $item->description);
        self::assertSame('Hachette', $item->publisher);
        self::assertSame('1959', $item->publishedDate);
        self::assertSame('https://example.com/cover.jpg', $item->coverUrl);
        self::assertTrue($item->hasNasTome);
        self::assertTrue($item->isCurrentlyReading);
        self::assertFalse($item->isFullyRead);
        self::assertFalse($item->isWishlist);
        self::assertFalse($item->isOneShot);
        self::assertSame(2, $item->tomesCount);
        self::assertSame(1, $item->readTomesCount);
        self::assertSame(1, $item->lastRead);
        self::assertSame(2, $item->lastBought);
        self::assertContains(3, $item->missingTomesNumbers);
        self::assertContains(4, $item->missingTomesNumbers);
        self::assertContains(5, $item->missingTomesNumbers);
        self::assertContains(1, $item->ownedTomesNumbers);
        self::assertContains(2, $item->ownedTomesNumbers);
    }

    public function testFindAllForApiWithEmptyDatabaseReturnsEmptyArray(): void
    {
        $result = $this->repository->findAllForApi();

        self::assertSame([], $result);
    }

    public function testFindWithFiltersUnknownReadingFilterReturnsAll(): void
    {
        $seriesA = EntityFactory::createComicSeries('Alpha');
        $seriesA->addTome(EntityFactory::createTome(1, read: true));

        $seriesB = EntityFactory::createComicSeries('Bravo');
        $seriesB->addTome(EntityFactory::createTome(1, read: false));

        $this->em->persist($seriesA);
        $this->em->persist($seriesB);
        $this->em->flush();

        // Valeur inconnue → le match default retourne null, pas de filtre appliqué
        $result = $this->repository->findWithFilters(new ComicSeriesFilter(reading: 'invalid_reading'));

        self::assertCount(2, $result);
    }

    public function testFindWithFiltersOnNasNullDoesNotFilter(): void
    {
        $withNas = EntityFactory::createComicSeries('With NAS');
        $withNas->addTome(EntityFactory::createTome(1, onNas: true));

        $withoutNas = EntityFactory::createComicSeries('Without NAS');
        $withoutNas->addTome(EntityFactory::createTome(1, onNas: false));

        $this->em->persist($withNas);
        $this->em->persist($withoutNas);
        $this->em->flush();

        // onNas null → pas de filtre, toutes les séries retournées
        $result = $this->repository->findWithFilters(new ComicSeriesFilter(onNas: null));

        self::assertCount(2, $result);
    }

    public function testFindAllForApiWithSeriesWithoutTomes(): void
    {
        $series = EntityFactory::createComicSeries('Empty Series');

        $this->em->persist($series);
        $this->em->flush();

        $result = $this->repository->findAllForApi();

        self::assertCount(1, $result);
        self::assertSame('Empty Series', $result[0]->title);
        self::assertSame(0, $result[0]->tomesCount);
        self::assertSame(0, $result[0]->readTomesCount);
        self::assertFalse($result[0]->hasNasTome);
    }

    // ---------------------------------------------------------------
    // findAllForApi — cache
    // ---------------------------------------------------------------

    public function testFindAllForApiReturnsSameResultOnConsecutiveCalls(): void
    {
        $series = EntityFactory::createComicSeries('Alpha');
        $this->em->persist($series);
        $this->em->flush();

        // Deux appels consécutifs doivent retourner le même résultat (cache)
        $firstResult = $this->repository->findAllForApi();
        $secondResult = $this->repository->findAllForApi();

        self::assertEquals($firstResult, $secondResult);
    }

    public function testFindAllForApiCacheInvalidatedAfterNewSeries(): void
    {
        $series = EntityFactory::createComicSeries('Alpha');
        $this->em->persist($series);
        $this->em->flush();

        // Premier appel → remplit le cache
        $firstResult = $this->repository->findAllForApi();
        self::assertCount(1, $firstResult);

        // Ajouter une nouvelle série via Doctrine (déclenche le listener)
        $newSeries = EntityFactory::createComicSeries('Bravo');
        $this->em->persist($newSeries);
        $this->em->flush();

        // Le cache doit être invalidé, nouveau résultat avec 2 séries
        $secondResult = $this->repository->findAllForApi();
        self::assertCount(2, $secondResult);
    }

    public function testFindAllForApiCacheInvalidatedAfterSeriesUpdate(): void
    {
        $series = EntityFactory::createComicSeries('Alpha');
        $this->em->persist($series);
        $this->em->flush();

        // Premier appel → remplit le cache
        $firstResult = $this->repository->findAllForApi();
        self::assertSame('Alpha', $firstResult[0]->title);

        // Modifier la série
        $series->setTitle('Alpha Modifié');
        $this->em->flush();

        // Le cache doit être invalidé, titre mis à jour
        $secondResult = $this->repository->findAllForApi();
        self::assertSame('Alpha Modifié', $secondResult[0]->title);
    }

    public function testFindAllForApiCacheInvalidatedAfterTomePersist(): void
    {
        $series = EntityFactory::createComicSeries('Alpha');
        $this->em->persist($series);
        $this->em->flush();

        // Premier appel → 0 tomes
        $firstResult = $this->repository->findAllForApi();
        self::assertSame(0, $firstResult[0]->tomesCount);

        // Ajouter un tome
        $tome = EntityFactory::createTome(1);
        $series->addTome($tome);
        $this->em->flush();

        // Le cache doit être invalidé, 1 tome maintenant
        $secondResult = $this->repository->findAllForApi();
        self::assertSame(1, $secondResult[0]->tomesCount);
    }

    // ---------------------------------------------------------------
    // findWithMissingLookupData
    // ---------------------------------------------------------------

    public function testFindWithMissingLookupDataReturnsSeriesWithoutDescription(): void
    {
        $missing = EntityFactory::createComicSeries('Missing');
        $complete = EntityFactory::createComicSeries('Complete');
        $complete->setDescription('Une description');
        $complete->setPublisher('Editeur');
        $complete->setPublishedDate('2024');
        $complete->setCoverUrl('https://example.com/cover.jpg');
        $complete->setLatestPublishedIssue(5);
        $author = EntityFactory::createAuthor('Auteur');
        $this->em->persist($author);
        $complete->addAuthor($author);

        $this->em->persist($complete);
        $this->em->persist($missing);
        $this->em->flush();

        $result = $this->repository->findWithMissingLookupData();

        self::assertCount(1, $result);
        self::assertSame('Missing', $result[0]->getTitle());
    }

    public function testFindWithMissingLookupDataSkipsLookupCompleted(): void
    {
        $alreadyLooked = EntityFactory::createComicSeries('Already Looked');
        $alreadyLooked->setLookupCompletedAt(new \DateTimeImmutable());

        $notLooked = EntityFactory::createComicSeries('Not Looked');

        $this->em->persist($alreadyLooked);
        $this->em->persist($notLooked);
        $this->em->flush();

        $result = $this->repository->findWithMissingLookupData();

        self::assertCount(1, $result);
        self::assertSame('Not Looked', $result[0]->getTitle());
    }

    public function testFindWithMissingLookupDataWithTypeFilter(): void
    {
        $manga = EntityFactory::createComicSeries('Naruto', ComicStatus::BUYING, ComicType::MANGA);
        $bd = EntityFactory::createComicSeries('Asterix', ComicStatus::BUYING, ComicType::BD);

        $this->em->persist($bd);
        $this->em->persist($manga);
        $this->em->flush();

        $result = $this->repository->findWithMissingLookupData(type: ComicType::MANGA);

        self::assertCount(1, $result);
        self::assertSame('Naruto', $result[0]->getTitle());
    }

    public function testFindWithMissingLookupDataWithLimit(): void
    {
        $this->em->persist(EntityFactory::createComicSeries('Alpha'));
        $this->em->persist(EntityFactory::createComicSeries('Bravo'));
        $this->em->persist(EntityFactory::createComicSeries('Charlie'));
        $this->em->flush();

        $result = $this->repository->findWithMissingLookupData(limit: 2);

        self::assertCount(2, $result);
    }

    public function testFindWithMissingLookupDataReturnsMissingCover(): void
    {
        $noCover = EntityFactory::createComicSeries('No Cover');
        $noCover->setDescription('Has description');
        $noCover->setPublisher('Editeur');
        $noCover->setPublishedDate('2024');
        $noCover->setLatestPublishedIssue(5);
        $author = EntityFactory::createAuthor('Auteur');
        $this->em->persist($author);
        $noCover->addAuthor($author);
        // Pas de coverUrl ni coverImage → doit être retourné

        $this->em->persist($noCover);
        $this->em->flush();

        $result = $this->repository->findWithMissingLookupData();

        self::assertCount(1, $result);
        self::assertSame('No Cover', $result[0]->getTitle());
    }

    public function testFindWithMissingLookupDataReturnsMissingAuthors(): void
    {
        $noAuthors = EntityFactory::createComicSeries('No Authors');
        $noAuthors->setDescription('Has description');
        $noAuthors->setPublisher('Editeur');
        $noAuthors->setPublishedDate('2024');
        $noAuthors->setCoverUrl('https://example.com/cover.jpg');
        $noAuthors->setLatestPublishedIssue(5);
        // Pas d'auteurs → doit être retourné

        $this->em->persist($noAuthors);
        $this->em->flush();

        $result = $this->repository->findWithMissingLookupData();

        self::assertCount(1, $result);
        self::assertSame('No Authors', $result[0]->getTitle());
    }

    // ---------------------------------------------------------------
    // findForMergeDetection
    // ---------------------------------------------------------------

    public function testFindForMergeDetectionReturnsUncheckedSeries(): void
    {
        $unchecked = EntityFactory::createComicSeries('Unchecked');

        $checked = EntityFactory::createComicSeries('Checked');
        $checked->setMergeCheckedAt(new \DateTimeImmutable());

        $this->em->persist($checked);
        $this->em->persist($unchecked);
        $this->em->flush();

        $result = $this->repository->findForMergeDetection();

        self::assertCount(1, $result);
        self::assertSame('Unchecked', $result[0]->getTitle());
    }

    public function testFindForMergeDetectionWithForceReturnsAll(): void
    {
        $unchecked = EntityFactory::createComicSeries('Alpha');

        $checked = EntityFactory::createComicSeries('Bravo');
        $checked->setMergeCheckedAt(new \DateTimeImmutable());

        $this->em->persist($checked);
        $this->em->persist($unchecked);
        $this->em->flush();

        $result = $this->repository->findForMergeDetection(force: true);

        self::assertCount(2, $result);
        self::assertSame('Alpha', $result[0]->getTitle());
        self::assertSame('Bravo', $result[1]->getTitle());
    }

    public function testFindForMergeDetectionFiltersByType(): void
    {
        $bd = EntityFactory::createComicSeries('Asterix', ComicStatus::BUYING, ComicType::BD);
        $manga = EntityFactory::createComicSeries('Naruto', ComicStatus::BUYING, ComicType::MANGA);

        $this->em->persist($bd);
        $this->em->persist($manga);
        $this->em->flush();

        $result = $this->repository->findForMergeDetection(type: ComicType::BD);

        self::assertCount(1, $result);
        self::assertSame('Asterix', $result[0]->getTitle());
    }

    // ---------------------------------------------------------------
    // findPurgeable
    // ---------------------------------------------------------------

    public function testFindPurgeableReturnsSeriesDeletedBeforeCutoff(): void
    {
        $old = EntityFactory::createComicSeries('Old Deleted');
        $old->delete();
        // Simuler une suppression il y a 60 jours
        $reflection = new \ReflectionProperty($old, 'deletedAt');
        $reflection->setValue($old, new \DateTime('-60 days'));

        $recent = EntityFactory::createComicSeries('Recent Deleted');
        $recent->delete();

        $active = EntityFactory::createComicSeries('Active');

        $this->em->persist($old);
        $this->em->persist($recent);
        $this->em->persist($active);
        $this->em->flush();

        $result = $this->repository->findPurgeable(30);

        self::assertCount(1, $result);
        self::assertSame('Old Deleted', $result[0]->getTitle());
    }

    public function testFindPurgeableReturnsEmptyWhenNoPurgeableSeries(): void
    {
        $active = EntityFactory::createComicSeries('Active');
        $this->em->persist($active);
        $this->em->flush();

        $result = $this->repository->findPurgeable(30);

        self::assertSame([], $result);
    }

    // ---------------------------------------------------------------
    // findTrashed
    // ---------------------------------------------------------------

    public function testFindTrashedReturnsSoftDeletedOrderedByDeletedAtDesc(): void
    {
        $deleted1 = EntityFactory::createComicSeries('First Deleted');
        $deleted1->delete();
        $reflection = new \ReflectionProperty($deleted1, 'deletedAt');
        $reflection->setValue($deleted1, new \DateTime('-2 days'));

        $deleted2 = EntityFactory::createComicSeries('Second Deleted');
        $deleted2->delete();

        $active = EntityFactory::createComicSeries('Active');

        $this->em->persist($deleted1);
        $this->em->persist($deleted2);
        $this->em->persist($active);
        $this->em->flush();

        $result = $this->repository->findTrashed();

        self::assertCount(2, $result);
        // Le plus récemment supprimé en premier
        self::assertSame('Second Deleted', $result[0]->getTitle());
        self::assertSame('First Deleted', $result[1]->getTitle());
    }

    public function testFindTrashedReturnsEmptyWhenNoDeletedSeries(): void
    {
        $active = EntityFactory::createComicSeries('Active');
        $this->em->persist($active);
        $this->em->flush();

        $result = $this->repository->findTrashed();

        self::assertSame([], $result);
    }

    // ---------------------------------------------------------------
    // findBuyingForReleaseCheck
    // ---------------------------------------------------------------

    public function testFindBuyingForReleaseCheckReturnsBuyingNonOneShotNonComplete(): void
    {
        $buying = EntityFactory::createComicSeries('Buying Series');
        // status=BUYING, isOneShot=false, latestPublishedIssueComplete=false (defaults)

        $finished = EntityFactory::createComicSeries('Finished Series', ComicStatus::FINISHED);

        $oneShot = EntityFactory::createComicSeries('One Shot');
        $oneShot->setIsOneShot(true);

        $complete = EntityFactory::createComicSeries('Complete Series');
        $complete->setLatestPublishedIssueComplete(true);

        $this->em->persist($buying);
        $this->em->persist($complete);
        $this->em->persist($finished);
        $this->em->persist($oneShot);
        $this->em->flush();

        $result = $this->repository->findBuyingForReleaseCheck();

        self::assertCount(1, $result);
        self::assertSame('Buying Series', $result[0]->getTitle());
    }

    public function testFindBuyingForReleaseCheckOrdersByNewReleasesCheckedAtNullFirst(): void
    {
        $neverChecked = EntityFactory::createComicSeries('Never Checked');

        $checkedOld = EntityFactory::createComicSeries('Checked Old');
        $checkedOld->setNewReleasesCheckedAt(new \DateTimeImmutable('-7 days'));

        $checkedRecent = EntityFactory::createComicSeries('Checked Recent');
        $checkedRecent->setNewReleasesCheckedAt(new \DateTimeImmutable('-1 day'));

        $this->em->persist($checkedOld);
        $this->em->persist($checkedRecent);
        $this->em->persist($neverChecked);
        $this->em->flush();

        $result = $this->repository->findBuyingForReleaseCheck();

        self::assertCount(3, $result);
        self::assertSame('Never Checked', $result[0]->getTitle());
        self::assertSame('Checked Old', $result[1]->getTitle());
        self::assertSame('Checked Recent', $result[2]->getTitle());
    }

    public function testFindBuyingForReleaseCheckRespectsLimit(): void
    {
        $this->em->persist(EntityFactory::createComicSeries('Alpha'));
        $this->em->persist(EntityFactory::createComicSeries('Bravo'));
        $this->em->persist(EntityFactory::createComicSeries('Charlie'));
        $this->em->flush();

        $result = $this->repository->findBuyingForReleaseCheck(limit: 2);

        self::assertCount(2, $result);
    }

    public function testFindBuyingForReleaseCheckNullLimitReturnsAll(): void
    {
        $this->em->persist(EntityFactory::createComicSeries('Alpha'));
        $this->em->persist(EntityFactory::createComicSeries('Bravo'));
        $this->em->flush();

        $result = $this->repository->findBuyingForReleaseCheck();

        self::assertCount(2, $result);
    }

    public function testFindWithMissingLookupDataForceIgnoresLookupCompletedAt(): void
    {
        $alreadyLooked = EntityFactory::createComicSeries('Already Looked');
        $alreadyLooked->setLookupCompletedAt(new \DateTimeImmutable());

        $this->em->persist($alreadyLooked);
        $this->em->flush();

        $result = $this->repository->findWithMissingLookupData(force: true);

        self::assertCount(1, $result);
        self::assertSame('Already Looked', $result[0]->getTitle());
    }
}
