<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\ComicSeries;
use App\Entity\Tome;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
use App\Repository\ComicSeriesRepository;
use Doctrine\ORM\EntityManagerInterface;
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

    protected function tearDown(): void
    {
        parent::tearDown();
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

        // Nettoyer
        $this->em->remove($library);
        $this->em->remove($wishlist);
        $this->em->flush();
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

        // Nettoyer
        $this->em->remove($bd);
        $this->em->remove($manga);
        $this->em->flush();
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

        // Nettoyer
        $this->em->remove($buying);
        $this->em->remove($finished);
        $this->em->flush();
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

        // Nettoyer
        $this->em->remove($seriesOnNas);
        $this->em->remove($seriesNotOnNas);
        $this->em->flush();
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

        // Nettoyer
        $this->em->remove($seriesOnNas);
        $this->em->remove($seriesNotOnNas);
        $this->em->flush();
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

        // Nettoyer
        $this->em->remove($series1);
        $this->em->remove($series2);
        $this->em->flush();
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

        // Nettoyer
        $this->em->remove($series);
        $this->em->flush();
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

        // Nettoyer
        $this->em->remove($seriesZ);
        $this->em->remove($seriesA);
        $this->em->flush();
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

        // Nettoyer
        $this->em->remove($seriesZ);
        $this->em->remove($seriesA);
        $this->em->flush();
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

        // Nettoyer
        $this->em->remove($series);
        $this->em->flush();
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

        // Nettoyer
        $this->em->remove($stopped);
        $this->em->remove($buying);
        $this->em->flush();
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

        // Nettoyer
        $this->em->remove($series);
        $this->em->flush();
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
