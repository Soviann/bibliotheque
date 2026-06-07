<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup;

use App\Entity\ComicSeries;
use App\Enum\ComicType;
use App\Message\EnrichSeriesMessage;
use App\Repository\ComicSeriesRepository;
use App\Service\Lookup\BatchLookupService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class BatchLookupServiceTest extends TestCase
{
    private ComicSeriesRepository&MockObject $comicSeriesRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private MessageBusInterface&MockObject $messageBus;
    private BatchLookupService $service;

    protected function setUp(): void
    {
        $this->comicSeriesRepository = $this->createMock(ComicSeriesRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->service = new BatchLookupService(
            $this->comicSeriesRepository,
            $this->entityManager,
            $this->messageBus,
        );
    }

    #[Test]
    public function countSeriesToProcessDelegatesToRepository(): void
    {
        $this->comicSeriesRepository
            ->expects(self::once())
            ->method('findWithMissingLookupData')
            ->with(type: ComicType::MANGA, limit: null, force: true)
            ->willReturn([$this->seriesStub(1), $this->seriesStub(2)]);

        self::assertSame(2, $this->service->countSeriesToProcess(ComicType::MANGA, true));
    }

    #[Test]
    public function queueDispatchesOneMessagePerSeries(): void
    {
        $this->comicSeriesRepository
            ->method('findWithMissingLookupData')
            ->willReturn([$this->seriesStub(1), $this->seriesStub(2)]);

        // Sans force : pas de réinitialisation.
        $this->entityManager->expects(self::never())->method('flush');

        $dispatched = [];
        $this->messageBus
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (EnrichSeriesMessage $message) use (&$dispatched): Envelope {
                $dispatched[] = $message->seriesId;

                return new Envelope($message);
            });

        self::assertSame(2, $this->service->queue());
        self::assertSame([1, 2], $dispatched);
    }

    #[Test]
    public function queueWithForceResetsLookupCompletedAtThenDispatches(): void
    {
        $series = $this->createMock(ComicSeries::class);
        $series->method('getId')->willReturn(7);
        $series->expects(self::once())->method('setLookupCompletedAt')->with(null);

        $this->comicSeriesRepository
            ->method('findWithMissingLookupData')
            ->willReturn([$series]);

        $this->entityManager->expects(self::once())->method('flush');

        $this->messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                static fn (EnrichSeriesMessage $m): bool => 7 === $m->seriesId && 'batch' === $m->triggeredBy,
            ))
            ->willReturnCallback(static fn (EnrichSeriesMessage $m): Envelope => new Envelope($m));

        self::assertSame(1, $this->service->queue(force: true));
    }

    #[Test]
    public function queueSkipsSeriesWithoutId(): void
    {
        $this->comicSeriesRepository
            ->method('findWithMissingLookupData')
            ->willReturn([$this->seriesStub(null), $this->seriesStub(5)]);

        // Seule la série avec un id est dispatchée…
        $this->messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static fn (EnrichSeriesMessage $m): Envelope => new Envelope($m));

        // …mais le total renvoyé reflète toutes les séries trouvées.
        self::assertSame(2, $this->service->queue());
    }

    #[Test]
    public function queueReturnsZeroForNoSeries(): void
    {
        $this->comicSeriesRepository
            ->method('findWithMissingLookupData')
            ->willReturn([]);

        $this->messageBus->expects(self::never())->method('dispatch');

        self::assertSame(0, $this->service->queue());
    }

    private function seriesStub(?int $id): ComicSeries&Stub
    {
        $series = $this->createStub(ComicSeries::class);
        $series->method('getId')->willReturn($id);

        return $series;
    }
}
