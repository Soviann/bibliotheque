<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\BatchLookupProgress;
use App\Enum\BatchLookupStatus;
use App\Enum\ComicType;
use App\Repository\ComicSeriesRepository;
use App\Service\BatchLookupService;
use App\Service\Lookup\LookupApplier;
use App\Service\Lookup\LookupOrchestrator;
use App\Service\Lookup\Contract\LookupResult;
use App\Tests\Factory\EntityFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class BatchLookupServiceTest extends TestCase
{
    private ComicSeriesRepository&MockObject $comicSeriesRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private LookupApplier&MockObject $lookupApplier;
    private LookupOrchestrator&MockObject $lookupOrchestrator;
    private BatchLookupService $service;

    protected function setUp(): void
    {
        $this->comicSeriesRepository = $this->createMock(ComicSeriesRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->lookupApplier = $this->createMock(LookupApplier::class);
        $this->lookupOrchestrator = $this->createMock(LookupOrchestrator::class);

        $this->service = new BatchLookupService(
            $this->comicSeriesRepository,
            $this->entityManager,
            $this->lookupApplier,
            $this->lookupOrchestrator,
        );
    }

    #[Test]
    public function countSeriesToProcessDelegatesToRepository(): void
    {
        $series = [
            EntityFactory::createComicSeries('A'),
            EntityFactory::createComicSeries('B'),
        ];

        $this->comicSeriesRepository
            ->expects(self::once())
            ->method('findWithMissingLookupData')
            ->with(type: ComicType::MANGA, limit: null, force: true)
            ->willReturn($series);

        self::assertSame(2, $this->service->countSeriesToProcess(ComicType::MANGA, true));
    }

    #[Test]
    public function runYieldsUpdatedProgressWhenFieldsUpdated(): void
    {
        $series = EntityFactory::createComicSeries('Naruto', type: ComicType::MANGA);

        $this->comicSeriesRepository
            ->method('findWithMissingLookupData')
            ->willReturn([$series]);

        $result = new LookupResult(source: 'test');

        $this->lookupOrchestrator
            ->method('lookupByTitle')
            ->willReturn($result);

        $this->lookupOrchestrator
            ->method('getLastApiMessages')
            ->willReturn([]);

        $this->lookupApplier
            ->method('apply')
            ->willReturn(['description', 'publisher']);

        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        $progressItems = \iterator_to_array($this->service->run(delay: 0));

        self::assertCount(1, $progressItems);

        /** @var BatchLookupProgress $progress */
        $progress = $progressItems[0];
        self::assertSame(1, $progress->current);
        self::assertSame(1, $progress->total);
        self::assertSame('Naruto', $progress->seriesTitle);
        self::assertSame(BatchLookupStatus::UPDATED, $progress->status);
        self::assertSame(['description', 'publisher'], $progress->updatedFields);
    }

    #[Test]
    public function runYieldsSkippedWhenNoResult(): void
    {
        $series = EntityFactory::createComicSeries('Unknown');

        $this->comicSeriesRepository
            ->method('findWithMissingLookupData')
            ->willReturn([$series]);

        $this->lookupOrchestrator
            ->method('lookupByTitle')
            ->willReturn(null);

        $this->lookupOrchestrator
            ->method('getLastApiMessages')
            ->willReturn([]);

        $progressItems = \iterator_to_array($this->service->run(delay: 0));

        self::assertCount(1, $progressItems);
        self::assertSame(BatchLookupStatus::SKIPPED, $progressItems[0]->status);
    }

    #[Test]
    public function runYieldsSkippedWhenNoFieldsUpdated(): void
    {
        $series = EntityFactory::createComicSeries('Complete');

        $this->comicSeriesRepository
            ->method('findWithMissingLookupData')
            ->willReturn([$series]);

        $result = new LookupResult(source: 'test');

        $this->lookupOrchestrator
            ->method('lookupByTitle')
            ->willReturn($result);

        $this->lookupOrchestrator
            ->method('getLastApiMessages')
            ->willReturn([]);

        $this->lookupApplier
            ->method('apply')
            ->willReturn([]);

        $progressItems = \iterator_to_array($this->service->run(delay: 0));

        self::assertCount(1, $progressItems);
        self::assertSame(BatchLookupStatus::SKIPPED, $progressItems[0]->status);
    }

    #[Test]
    public function runYieldsFailedOnPersistentRateLimit(): void
    {
        $series = EntityFactory::createComicSeries('Rate Limited');

        $this->comicSeriesRepository
            ->method('findWithMissingLookupData')
            ->willReturn([$series]);

        $this->lookupOrchestrator
            ->method('lookupByTitle')
            ->willReturn(null);

        $this->lookupOrchestrator
            ->method('hasRateLimitError')
            ->willReturn(true);

        $progressItems = \iterator_to_array($this->service->run(delay: 0));

        self::assertCount(1, $progressItems);
        self::assertSame(BatchLookupStatus::FAILED, $progressItems[0]->status);
    }

    #[Test]
    public function runDoesNotFlushInDryRunMode(): void
    {
        $series = EntityFactory::createComicSeries('DryRun');

        $this->comicSeriesRepository
            ->method('findWithMissingLookupData')
            ->willReturn([$series]);

        $this->lookupOrchestrator
            ->method('lookupByTitle')
            ->willReturn(null);

        $this->lookupOrchestrator
            ->method('getLastApiMessages')
            ->willReturn([]);

        $this->entityManager
            ->expects(self::never())
            ->method('flush');

        \iterator_to_array($this->service->run(delay: 0, dryRun: true));
    }

    #[Test]
    public function runYieldsEmptyForNoSeries(): void
    {
        $this->comicSeriesRepository
            ->method('findWithMissingLookupData')
            ->willReturn([]);

        $progressItems = \iterator_to_array($this->service->run(delay: 0));

        self::assertCount(0, $progressItems);
    }
}
