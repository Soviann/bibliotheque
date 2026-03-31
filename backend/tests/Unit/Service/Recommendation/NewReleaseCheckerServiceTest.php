<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Recommendation;

use App\DTO\NewReleaseProgress;
use App\Enum\BatchLookupStatus;
use App\Repository\ComicSeriesRepository;
use App\Service\Lookup\Contract\LookupResult;
use App\Service\Lookup\LookupOrchestrator;
use App\Service\Recommendation\NewReleaseCheckerService;
use App\Tests\Factory\EntityFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour NewReleaseCheckerService.
 */
final class NewReleaseCheckerServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private LookupOrchestrator&MockObject $lookupOrchestrator;
    private NewReleaseCheckerService $service;
    private ComicSeriesRepository&MockObject $repository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->lookupOrchestrator = $this->createMock(LookupOrchestrator::class);
        $this->repository = $this->createMock(ComicSeriesRepository::class);

        $this->service = new NewReleaseCheckerService(
            $this->entityManager,
            $this->lookupOrchestrator,
            $this->repository,
        );
    }

    public function testRunWithNoSeries(): void
    {
        $this->repository->method('findBuyingForReleaseCheck')->willReturn([]);

        $results = \iterator_to_array($this->service->run(dryRun: false, limit: null));

        self::assertSame([], $results);
    }

    public function testRunUpdatesSeriesWithNewLatestPublishedIssue(): void
    {
        $series = EntityFactory::createComicSeries('Naruto');
        $series->setLatestPublishedIssue(70);
        // Ajouter des tomes existants 1-70
        for ($i = 1; $i <= 70; ++$i) {
            $series->addTome(EntityFactory::createTome($i));
        }

        $this->repository->method('findBuyingForReleaseCheck')->willReturn([$series]);

        $result = new LookupResult(latestPublishedIssue: 73);
        $this->lookupOrchestrator->method('lookupByTitle')->willReturn($result);
        $this->lookupOrchestrator->method('getLastApiMessages')->willReturn([]);

        $this->entityManager->expects(self::once())->method('flush');

        $progresses = \iterator_to_array($this->service->run(dryRun: false, limit: null));

        self::assertCount(1, $progresses);

        /** @var NewReleaseProgress $progress */
        $progress = $progresses[0];
        self::assertSame(1, $progress->current);
        self::assertSame(1, $progress->total);
        self::assertSame('Naruto', $progress->seriesTitle);
        self::assertSame(BatchLookupStatus::UPDATED, $progress->status);
        self::assertSame(73, $progress->newLatestIssue);
        self::assertSame(70, $progress->previousLatestIssue);
        self::assertFalse($progress->stoppedByRateLimit);

        // Vérifie que la série a été mise à jour
        self::assertSame(73, $series->getLatestPublishedIssue());
        self::assertNotNull($series->getLatestPublishedIssueUpdatedAt());
        self::assertNotNull($series->getNewReleasesCheckedAt());

        // Vérifie que les tomes 71-73 ont été créés
        $tomeNumbers = $series->getTomes()->map(static fn ($t): int => $t->getNumber())->toArray();
        self::assertContains(71, $tomeNumbers);
        self::assertContains(72, $tomeNumbers);
        self::assertContains(73, $tomeNumbers);
    }

    public function testRunSkipsWhenNoChange(): void
    {
        $series = EntityFactory::createComicSeries('One Piece');
        $series->setLatestPublishedIssue(105);

        $this->repository->method('findBuyingForReleaseCheck')->willReturn([$series]);

        $result = new LookupResult(latestPublishedIssue: 105);
        $this->lookupOrchestrator->method('lookupByTitle')->willReturn($result);
        $this->lookupOrchestrator->method('getLastApiMessages')->willReturn([]);

        $progresses = \iterator_to_array($this->service->run(dryRun: false, limit: null));

        self::assertCount(1, $progresses);
        self::assertSame(BatchLookupStatus::SKIPPED, $progresses[0]->status);
        self::assertNull($progresses[0]->newLatestIssue);
        self::assertNotNull($series->getNewReleasesCheckedAt());
    }

    public function testRunSkipsWhenLookupReturnsNull(): void
    {
        $series = EntityFactory::createComicSeries('Unknown');
        $series->setLatestPublishedIssue(5);

        $this->repository->method('findBuyingForReleaseCheck')->willReturn([$series]);

        $this->lookupOrchestrator->method('lookupByTitle')->willReturn(null);
        $this->lookupOrchestrator->method('getLastApiMessages')->willReturn([]);

        $progresses = \iterator_to_array($this->service->run(dryRun: false, limit: null));

        self::assertCount(1, $progresses);
        self::assertSame(BatchLookupStatus::SKIPPED, $progresses[0]->status);
    }

    public function testRunSkipsWhenLookupReturnsLowerValue(): void
    {
        $series = EntityFactory::createComicSeries('Series');
        $series->setLatestPublishedIssue(10);

        $this->repository->method('findBuyingForReleaseCheck')->willReturn([$series]);

        $result = new LookupResult(latestPublishedIssue: 8);
        $this->lookupOrchestrator->method('lookupByTitle')->willReturn($result);
        $this->lookupOrchestrator->method('getLastApiMessages')->willReturn([]);

        $progresses = \iterator_to_array($this->service->run(dryRun: false, limit: null));

        self::assertSame(BatchLookupStatus::SKIPPED, $progresses[0]->status);
        // latestPublishedIssue ne doit pas être réduit
        self::assertSame(10, $series->getLatestPublishedIssue());
    }

    public function testRunStopsOnRateLimit(): void
    {
        $series1 = EntityFactory::createComicSeries('Alpha');
        $series2 = EntityFactory::createComicSeries('Bravo');

        $this->repository->method('findBuyingForReleaseCheck')->willReturn([$series1, $series2]);

        $this->lookupOrchestrator->method('lookupByTitle')->willReturn(null);
        $this->lookupOrchestrator->method('hasRateLimitError')->willReturn(true);

        $progresses = \iterator_to_array($this->service->run(dryRun: false, limit: null));

        // Seule la première série est traitée, puis arrêt
        self::assertCount(1, $progresses);
        self::assertTrue($progresses[0]->stoppedByRateLimit);
        self::assertSame(BatchLookupStatus::FAILED, $progresses[0]->status);
    }

    public function testDryRunDoesNotPersist(): void
    {
        $series = EntityFactory::createComicSeries('Naruto');
        $series->setLatestPublishedIssue(70);

        $this->repository->method('findBuyingForReleaseCheck')->willReturn([$series]);

        $result = new LookupResult(latestPublishedIssue: 73);
        $this->lookupOrchestrator->method('lookupByTitle')->willReturn($result);
        $this->lookupOrchestrator->method('getLastApiMessages')->willReturn([]);

        $this->entityManager->expects(self::never())->method('flush');

        $progresses = \iterator_to_array($this->service->run(dryRun: true, limit: null));

        self::assertCount(1, $progresses);
        self::assertSame(BatchLookupStatus::UPDATED, $progresses[0]->status);
        // En dry-run, la série ne doit pas être modifiée
        self::assertSame(70, $series->getLatestPublishedIssue());
        self::assertNull($series->getNewReleasesCheckedAt());
    }

    public function testRunWithLimitPassesLimitToRepository(): void
    {
        $this->repository->expects(self::once())
            ->method('findBuyingForReleaseCheck')
            ->with(5)
            ->willReturn([]);

        \iterator_to_array($this->service->run(dryRun: false, limit: 5));
    }

    public function testRunCreatesNewTomesWithDefaultFlags(): void
    {
        $series = EntityFactory::createComicSeries('Test');
        $series->setLatestPublishedIssue(2);
        $series->setDefaultTomeBought(true);
        $series->setDefaultTomeOnNas(true);
        $series->setDefaultTomeRead(false);
        $series->addTome(EntityFactory::createTome(1, bought: true));
        $series->addTome(EntityFactory::createTome(2, bought: true));

        $this->repository->method('findBuyingForReleaseCheck')->willReturn([$series]);

        $result = new LookupResult(latestPublishedIssue: 4);
        $this->lookupOrchestrator->method('lookupByTitle')->willReturn($result);
        $this->lookupOrchestrator->method('getLastApiMessages')->willReturn([]);

        \iterator_to_array($this->service->run(dryRun: false, limit: null));

        // Vérifie tomes 3 et 4 créés avec les bons flags
        $newTomes = $series->getTomes()->filter(static fn ($t): bool => $t->getNumber() >= 3);
        self::assertCount(2, $newTomes);

        foreach ($newTomes as $tome) {
            self::assertTrue($tome->isBought());
            self::assertTrue($tome->isOnNas());
            self::assertFalse($tome->isRead());
        }
    }

    public function testRunSkipsWhenLookupReturnsNullLatestPublishedIssue(): void
    {
        $series = EntityFactory::createComicSeries('Series');
        $series->setLatestPublishedIssue(10);

        $this->repository->method('findBuyingForReleaseCheck')->willReturn([$series]);

        $result = new LookupResult(latestPublishedIssue: null);
        $this->lookupOrchestrator->method('lookupByTitle')->willReturn($result);
        $this->lookupOrchestrator->method('getLastApiMessages')->willReturn([]);

        $progresses = \iterator_to_array($this->service->run(dryRun: false, limit: null));

        self::assertSame(BatchLookupStatus::SKIPPED, $progresses[0]->status);
    }

    public function testRunHandlesSeriesWithNullLatestPublishedIssue(): void
    {
        $series = EntityFactory::createComicSeries('New Series');
        // latestPublishedIssue is null

        $this->repository->method('findBuyingForReleaseCheck')->willReturn([$series]);

        $result = new LookupResult(latestPublishedIssue: 5);
        $this->lookupOrchestrator->method('lookupByTitle')->willReturn($result);
        $this->lookupOrchestrator->method('getLastApiMessages')->willReturn([]);

        $progresses = \iterator_to_array($this->service->run(dryRun: false, limit: null));

        self::assertSame(BatchLookupStatus::UPDATED, $progresses[0]->status);
        self::assertSame(5, $progresses[0]->newLatestIssue);
        self::assertNull($progresses[0]->previousLatestIssue);
        self::assertSame(5, $series->getLatestPublishedIssue());
    }
}
