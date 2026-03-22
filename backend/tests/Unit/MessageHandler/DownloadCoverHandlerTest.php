<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\ComicSeries;
use App\Message\DownloadCoverMessage;
use App\MessageHandler\DownloadCoverHandler;
use App\Repository\ComicSeriesRepository;
use App\Service\Cover\CoverDownloader;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Tests unitaires pour le handler de téléchargement de couverture asynchrone.
 */
final class DownloadCoverHandlerTest extends TestCase
{
    private MockObject&ComicSeriesRepository $comicSeriesRepository;
    private MockObject&CoverDownloader $coverDownloader;
    private DownloadCoverHandler $handler;
    private MockObject&EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->comicSeriesRepository = $this->createMock(ComicSeriesRepository::class);
        $this->coverDownloader = $this->createMock(CoverDownloader::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->handler = new DownloadCoverHandler(
            $this->comicSeriesRepository,
            $this->coverDownloader,
            $this->entityManager,
            new NullLogger(),
        );
    }

    /**
     * Teste que le handler télécharge et persiste la couverture.
     */
    public function testDownloadsAndFlushes(): void
    {
        $series = new ComicSeries();
        $series->setTitle('Test');

        $this->comicSeriesRepository->expects(self::once())
            ->method('find')
            ->with(42)
            ->willReturn($series);

        $this->coverDownloader->expects(self::once())
            ->method('downloadAndStore')
            ->with($series, 'https://example.com/cover.jpg')
            ->willReturn(true);

        $this->entityManager->expects(self::once())->method('flush');

        ($this->handler)(new DownloadCoverMessage(42, 'https://example.com/cover.jpg'));
    }

    /**
     * Teste que le handler ne flush pas si le téléchargement échoue.
     */
    public function testDoesNotFlushOnDownloadFailure(): void
    {
        $series = new ComicSeries();
        $series->setTitle('Test');

        $this->comicSeriesRepository->expects(self::once())
            ->method('find')
            ->with(42)
            ->willReturn($series);

        $this->coverDownloader->expects(self::once())
            ->method('downloadAndStore')
            ->with($series, 'https://example.com/cover.jpg')
            ->willReturn(false);

        $this->entityManager->expects(self::never())->method('flush');

        ($this->handler)(new DownloadCoverMessage(42, 'https://example.com/cover.jpg'));
    }

    /**
     * Teste que le handler ignore les séries introuvables.
     */
    public function testSkipsIfSeriesNotFound(): void
    {
        $this->comicSeriesRepository->expects(self::once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->coverDownloader->expects(self::never())->method('downloadAndStore');
        $this->entityManager->expects(self::never())->method('flush');

        ($this->handler)(new DownloadCoverMessage(999, 'https://example.com/cover.jpg'));
    }
}
