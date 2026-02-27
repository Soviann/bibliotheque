<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\ComicSeries;
use App\Entity\Tome;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
use App\Repository\ComicSeriesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests d'intégration pour ComicSeriesRepository.
 */
class ComicSeriesRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ComicSeriesRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = $this->em->getRepository(ComicSeries::class);
    }

    /**
     * Teste findWithFilters avec filtre isWishlist.
     */
    public function testFindWithFiltersWishlistFilter(): void
    {
        $library = $this->createSeries('Library Series Filter Test', false);
        $wishlist = $this->createSeries('Wishlist Series Filter Test', true);
        $this->em->flush();

        $libraryResults = $this->repository->findWithFilters(['isWishlist' => false]);
        $wishlistResults = $this->repository->findWithFilters(['isWishlist' => true]);

        $libraryTitles = \array_map(static fn (ComicSeries $s): string => $s->getTitle(), $libraryResults);
        $wishlistTitles = \array_map(static fn (ComicSeries $s): string => $s->getTitle(), $wishlistResults);

        self::assertContains('Library Series Filter Test', $libraryTitles);
        self::assertNotContains('Wishlist Series Filter Test', $libraryTitles);
        self::assertContains('Wishlist Series Filter Test', $wishlistTitles);
        self::assertNotContains('Library Series Filter Test', $wishlistTitles);
    }

    /**
     * Teste findWithFilters avec filtre type.
     */
    public function testFindWithFiltersTypeFilter(): void
    {
        $bd = $this->createSeries('BD Series Test', false, ComicType::BD);
        $manga = $this->createSeries('Manga Series Test', false, ComicType::MANGA);
        $this->em->flush();

        $bdResults = $this->repository->findWithFilters([
            'isWishlist' => false,
            'type' => ComicType::BD,
        ]);

        $titles = \array_map(static fn (ComicSeries $s): string => $s->getTitle(), $bdResults);

        self::assertContains('BD Series Test', $titles);
        self::assertNotContains('Manga Series Test', $titles);
    }

    /**
     * Teste findWithFilters avec filtre status.
     */
    public function testFindWithFiltersStatusFilter(): void
    {
        $buying = $this->createSeries('Buying Series Test', false, ComicType::BD, ComicStatus::BUYING);
        $finished = $this->createSeries('Finished Series Test', false, ComicType::BD, ComicStatus::FINISHED);
        $this->em->flush();

        $buyingResults = $this->repository->findWithFilters([
            'isWishlist' => false,
            'status' => ComicStatus::BUYING,
        ]);

        $titles = \array_map(static fn (ComicSeries $s): string => $s->getTitle(), $buyingResults);

        self::assertContains('Buying Series Test', $titles);
        self::assertNotContains('Finished Series Test', $titles);
    }

    /**
     * Teste findWithFilters avec filtre onNas=true.
     */
    public function testFindWithFiltersOnNasTrue(): void
    {
        $seriesOnNas = $this->createSeries('On NAS Repo Test', false);
        $tomeOnNas = new Tome();
        $tomeOnNas->setNumber(1);
        $tomeOnNas->setOnNas(true);
        $seriesOnNas->addTome($tomeOnNas);

        $seriesNotOnNas = $this->createSeries('Not On NAS Repo Test', false);
        $tomeNotOnNas = new Tome();
        $tomeNotOnNas->setNumber(1);
        $tomeNotOnNas->setOnNas(false);
        $seriesNotOnNas->addTome($tomeNotOnNas);

        $this->em->flush();

        $results = $this->repository->findWithFilters([
            'isWishlist' => false,
            'onNas' => true,
        ]);

        $titles = \array_map(static fn (ComicSeries $s): string => $s->getTitle(), $results);

        self::assertContains('On NAS Repo Test', $titles);
        self::assertNotContains('Not On NAS Repo Test', $titles);
    }

    /**
     * Teste findWithFilters avec filtre onNas=false.
     */
    public function testFindWithFiltersOnNasFalse(): void
    {
        $seriesOnNas = $this->createSeries('On NAS False Test', false);
        $tomeOnNas = new Tome();
        $tomeOnNas->setNumber(1);
        $tomeOnNas->setOnNas(true);
        $seriesOnNas->addTome($tomeOnNas);

        $seriesNotOnNas = $this->createSeries('Not On NAS False Test', false);
        $tomeNotOnNas = new Tome();
        $tomeNotOnNas->setNumber(1);
        $tomeNotOnNas->setOnNas(false);
        $seriesNotOnNas->addTome($tomeNotOnNas);

        $this->em->flush();

        $results = $this->repository->findWithFilters([
            'isWishlist' => false,
            'onNas' => false,
        ]);

        $titles = \array_map(static fn (ComicSeries $s): string => $s->getTitle(), $results);

        self::assertNotContains('On NAS False Test', $titles);
        self::assertContains('Not On NAS False Test', $titles);
    }

    /**
     * Teste findWithFilters avec filtre search par titre.
     */
    public function testFindWithFiltersSearchByTitle(): void
    {
        $series1 = $this->createSeries('UniqueSearchRepoTestXYZ', false);
        $series2 = $this->createSeries('Other Series', false);
        $this->em->flush();

        $results = $this->repository->findWithFilters([
            'isWishlist' => false,
            'search' => 'UniqueSearchRepo',
        ]);

        $titles = \array_map(static fn (ComicSeries $s): string => $s->getTitle(), $results);

        self::assertContains('UniqueSearchRepoTestXYZ', $titles);
        self::assertNotContains('Other Series', $titles);
    }

    /**
     * Teste findWithFilters avec filtre search par ISBN de tome.
     */
    public function testFindWithFiltersSearchByIsbn(): void
    {
        $series = $this->createSeries('ISBN Search Repo Test', false);
        $tome = new Tome();
        $tome->setNumber(1);
        $tome->setIsbn('978-2-999-88877-6');
        $series->addTome($tome);
        $this->em->flush();

        $results = $this->repository->findWithFilters([
            'isWishlist' => false,
            'search' => '978-2-999-88877-6',
        ]);

        $titles = \array_map(static fn (ComicSeries $s): string => $s->getTitle(), $results);

        self::assertContains('ISBN Search Repo Test', $titles);
    }

    /**
     * Teste findWithFilters avec filtre reading='reading' (en cours de lecture).
     */
    public function testFindWithFiltersReadingInProgress(): void
    {
        // Série en cours de lecture : 1 tome lu, 1 non lu
        $reading = $this->createSeries('Reading In Progress Test', false);
        $tomeRead = new Tome();
        $tomeRead->setNumber(1);
        $tomeRead->setRead(true);
        $reading->addTome($tomeRead);
        $tomeUnread = new Tome();
        $tomeUnread->setNumber(2);
        $tomeUnread->setRead(false);
        $reading->addTome($tomeUnread);

        // Série entièrement lue
        $fullyRead = $this->createSeries('Fully Read Test', false);
        $tomeAllRead = new Tome();
        $tomeAllRead->setNumber(1);
        $tomeAllRead->setRead(true);
        $fullyRead->addTome($tomeAllRead);

        // Série non lue
        $unread = $this->createSeries('Unread Test', false);
        $tomeNotRead = new Tome();
        $tomeNotRead->setNumber(1);
        $tomeNotRead->setRead(false);
        $unread->addTome($tomeNotRead);

        $this->em->flush();

        $results = $this->repository->findWithFilters([
            'isWishlist' => false,
            'reading' => 'reading',
        ]);

        $titles = \array_map(static fn (ComicSeries $s): string => $s->getTitle(), $results);

        self::assertContains('Reading In Progress Test', $titles);
        self::assertNotContains('Fully Read Test', $titles);
        self::assertNotContains('Unread Test', $titles);
    }

    /**
     * Teste findWithFilters avec filtre reading='read' (entièrement lus).
     */
    public function testFindWithFiltersReadingRead(): void
    {
        // Série entièrement lue
        $fullyRead = $this->createSeries('All Read Test', false);
        $tome1 = new Tome();
        $tome1->setNumber(1);
        $tome1->setRead(true);
        $fullyRead->addTome($tome1);
        $tome2 = new Tome();
        $tome2->setNumber(2);
        $tome2->setRead(true);
        $fullyRead->addTome($tome2);

        // Série en cours
        $reading = $this->createSeries('Partial Read Test', false);
        $tomeRead = new Tome();
        $tomeRead->setNumber(1);
        $tomeRead->setRead(true);
        $reading->addTome($tomeRead);
        $tomeUnread = new Tome();
        $tomeUnread->setNumber(2);
        $tomeUnread->setRead(false);
        $reading->addTome($tomeUnread);

        $this->em->flush();

        $results = $this->repository->findWithFilters([
            'isWishlist' => false,
            'reading' => 'read',
        ]);

        $titles = \array_map(static fn (ComicSeries $s): string => $s->getTitle(), $results);

        self::assertContains('All Read Test', $titles);
        self::assertNotContains('Partial Read Test', $titles);
    }

    /**
     * Teste findWithFilters avec filtre reading='unread' (non lus).
     */
    public function testFindWithFiltersReadingUnread(): void
    {
        // Série non lue
        $unread = $this->createSeries('Not Read Test', false);
        $tome = new Tome();
        $tome->setNumber(1);
        $tome->setRead(false);
        $unread->addTome($tome);

        // Série avec au moins 1 tome lu
        $partialRead = $this->createSeries('Some Read Test', false);
        $tomeRead = new Tome();
        $tomeRead->setNumber(1);
        $tomeRead->setRead(true);
        $partialRead->addTome($tomeRead);

        $this->em->flush();

        $results = $this->repository->findWithFilters([
            'isWishlist' => false,
            'reading' => 'unread',
        ]);

        $titles = \array_map(static fn (ComicSeries $s): string => $s->getTitle(), $results);

        self::assertContains('Not Read Test', $titles);
        self::assertNotContains('Some Read Test', $titles);
    }

    /**
     * Teste findWithFilters tri par titre ascendant.
     */
    public function testFindWithFiltersSortTitleAsc(): void
    {
        $seriesZ = $this->createSeries('Zorro Repo Test', false);
        $seriesA = $this->createSeries('Asterix Repo Test', false);
        $this->em->flush();

        $results = $this->repository->findWithFilters([
            'isWishlist' => false,
            'sort' => 'title_asc',
        ]);

        $asterixIndex = null;
        $zorroIndex = null;
        foreach ($results as $index => $series) {
            if ('Asterix Repo Test' === $series->getTitle()) {
                $asterixIndex = $index;
            }
            if ('Zorro Repo Test' === $series->getTitle()) {
                $zorroIndex = $index;
            }
        }

        self::assertNotNull($asterixIndex);
        self::assertNotNull($zorroIndex);
        self::assertLessThan($zorroIndex, $asterixIndex);
    }

    /**
     * Teste findWithFilters tri par titre descendant.
     */
    public function testFindWithFiltersSortTitleDesc(): void
    {
        $seriesZ = $this->createSeries('Zorro Desc Test', false);
        $seriesA = $this->createSeries('Asterix Desc Test', false);
        $this->em->flush();

        $results = $this->repository->findWithFilters([
            'isWishlist' => false,
            'sort' => 'title_desc',
        ]);

        $asterixIndex = null;
        $zorroIndex = null;
        foreach ($results as $index => $series) {
            if ('Asterix Desc Test' === $series->getTitle()) {
                $asterixIndex = $index;
            }
            if ('Zorro Desc Test' === $series->getTitle()) {
                $zorroIndex = $index;
            }
        }

        self::assertNotNull($asterixIndex);
        self::assertNotNull($zorroIndex);
        self::assertGreaterThan($zorroIndex, $asterixIndex);
    }

    /**
     * Teste que findWithFilters eager-load les tomes (pas de N+1).
     */
    public function testFindWithFiltersEagerLoadsTomes(): void
    {
        $series = $this->createSeries('Eager Load Test', false);
        $tome = new Tome();
        $tome->setNumber(1);
        $tome->setBought(true);
        $series->addTome($tome);
        $this->em->flush();
        $this->em->clear();

        $results = $this->repository->findWithFilters(['isWishlist' => false]);

        $testSeries = null;
        foreach ($results as $s) {
            if ('Eager Load Test' === $s->getTitle()) {
                $testSeries = $s;
                break;
            }
        }

        self::assertNotNull($testSeries);
        $tomes = $testSeries->getTomes();
        self::assertInstanceOf(PersistentCollection::class, $tomes);
        self::assertTrue($tomes->isInitialized(), 'Les tomes doivent être eager-loadés par findWithFilters()');
    }

    /**
     * Teste que search() eager-load les tomes (pas de N+1).
     */
    public function testSearchEagerLoadsTomes(): void
    {
        $series = $this->createSeries('Eager Search XYZ99', false);
        $tome = new Tome();
        $tome->setNumber(1);
        $series->addTome($tome);
        $this->em->flush();
        $this->em->clear();

        $results = $this->repository->search('Eager Search XYZ99');

        self::assertCount(1, $results);
        $tomes = $results[0]->getTomes();
        self::assertInstanceOf(PersistentCollection::class, $tomes);
        self::assertTrue($tomes->isInitialized(), 'Les tomes doivent être eager-loadés par search()');
    }

    /**
     * Teste la méthode search.
     */
    public function testSearch(): void
    {
        $series = $this->createSeries('Unique Searchable Title XYZ', false);
        $this->em->flush();

        $results = $this->repository->search('Unique Searchable');

        $titles = \array_map(static fn (ComicSeries $s): string => $s->getTitle(), $results);

        self::assertContains('Unique Searchable Title XYZ', $titles);
    }

    /**
     * Teste findByStatus.
     */
    public function testFindByStatus(): void
    {
        $stopped = $this->createSeries('Stopped Status Test', false, ComicType::BD, ComicStatus::STOPPED);
        $buying = $this->createSeries('Buying Status Test', false, ComicType::BD, ComicStatus::BUYING);
        $this->em->flush();

        $results = $this->repository->findByStatus(ComicStatus::STOPPED);

        $titles = \array_map(static fn (ComicSeries $s): string => $s->getTitle(), $results);

        self::assertContains('Stopped Status Test', $titles);
        self::assertNotContains('Buying Status Test', $titles);
    }

    /**
     * Teste findAllForApi retourne la structure attendue.
     */
    public function testFindAllForApiReturnsExpectedStructure(): void
    {
        $series = $this->createSeries('API Repo Test', false);
        $series->setLatestPublishedIssue(5);
        $tome = new Tome();
        $tome->setNumber(1);
        $tome->setBought(true);
        $series->addTome($tome);
        $this->em->flush();

        $results = $this->repository->findAllForApi();

        // Trouver notre série
        $testResult = null;
        foreach ($results as $result) {
            if ('API Repo Test' === $result['title']) {
                $testResult = $result;
                break;
            }
        }

        self::assertNotNull($testResult);
        self::assertIsInt($testResult['id']);
        self::assertSame('API Repo Test', $testResult['title']);
        self::assertSame('buying', $testResult['status']);
        self::assertSame('bd', $testResult['type']);
        self::assertSame(1, $testResult['tomesCount']);
        self::assertSame(5, $testResult['latestPublishedIssue']);
        self::assertIsArray($testResult['missingTomesNumbers']);
        self::assertIsArray($testResult['ownedTomesNumbers']);
        self::assertArrayHasKey('isCurrentlyReading', $testResult);
        self::assertArrayHasKey('isFullyRead', $testResult);
        self::assertArrayHasKey('lastRead', $testResult);
        self::assertArrayHasKey('lastReadComplete', $testResult);
        self::assertArrayHasKey('readTomesCount', $testResult);
    }

    /**
     * Teste findSoftDeleted retourne les séries supprimées.
     */
    public function testFindSoftDeleted(): void
    {
        $deleted = $this->createSeries('Soft Deleted Test');
        $deleted->delete();
        $active = $this->createSeries('Active Series Test');
        $this->em->flush();

        $results = $this->repository->findSoftDeleted();

        $titles = \array_map(static fn (ComicSeries $s): string => $s->getTitle(), $results);

        self::assertContains('Soft Deleted Test', $titles);
        self::assertNotContains('Active Series Test', $titles);
    }

    /**
     * Teste findSoftDeleted trie par deletedAt DESC.
     */
    public function testFindSoftDeletedOrderedByDeletedAtDesc(): void
    {
        $first = $this->createSeries('First Deleted');
        $first->delete();
        $this->em->flush();

        // Simuler un deletedAt plus récent
        $second = $this->createSeries('Second Deleted');
        $second->delete();
        $this->em->flush();

        $results = $this->repository->findSoftDeleted();

        $titles = \array_map(static fn (ComicSeries $s): string => $s->getTitle(), $results);
        $firstIdx = \array_search('First Deleted', $titles, true);
        $secondIdx = \array_search('Second Deleted', $titles, true);

        self::assertNotFalse($firstIdx);
        self::assertNotFalse($secondIdx);
        self::assertGreaterThan($secondIdx, $firstIdx);
    }

    /**
     * Teste findSoftDeletedById retourne la série soft-deleted.
     */
    public function testFindSoftDeletedByIdReturnsDeletedSeries(): void
    {
        $series = $this->createSeries('Find By Id Deleted Test');
        $series->delete();
        $this->em->flush();

        $result = $this->repository->findSoftDeletedById($series->getId());

        self::assertNotNull($result);
        self::assertSame('Find By Id Deleted Test', $result->getTitle());
    }

    /**
     * Teste findSoftDeletedById retourne null pour une série active.
     */
    public function testFindSoftDeletedByIdReturnsNullForActiveSeries(): void
    {
        $series = $this->createSeries('Active Find By Id Test');
        $this->em->flush();

        $result = $this->repository->findSoftDeletedById($series->getId());

        self::assertNull($result);
    }

    /**
     * Teste findSoftDeletedById retourne null pour un ID inexistant.
     */
    public function testFindSoftDeletedByIdReturnsNullForNonExistent(): void
    {
        $result = $this->repository->findSoftDeletedById(999999);

        self::assertNull($result);
    }

    /**
     * Crée et persiste une série pour les tests.
     * Note: isWishlist est dérivé du statut (WISHLIST → isWishlist=true).
     */
    private function createSeries(
        string $title,
        bool $isWishlist = false,
        ComicType $type = ComicType::BD,
        ComicStatus $status = ComicStatus::BUYING,
    ): ComicSeries {
        $series = new ComicSeries();
        $series->setTitle($title);
        $series->setType($type);
        // isWishlist est calculé à partir du statut
        $effectiveStatus = $isWishlist ? ComicStatus::WISHLIST : $status;
        $series->setStatus($effectiveStatus);
        $this->em->persist($series);

        return $series;
    }
}
