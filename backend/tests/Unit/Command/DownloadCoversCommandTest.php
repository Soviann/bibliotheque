<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\DownloadCoversCommand;
use App\Entity\ComicSeries;
use App\Repository\ComicSeriesRepository;
use App\Service\Cover\CoverDownloader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\EntityManagerClosed;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests unitaires pour la commande de téléchargement des couvertures.
 */
final class DownloadCoversCommandTest extends TestCase
{
    private MockObject&ComicSeriesRepository $comicSeriesRepository;
    private MockObject&CoverDownloader $coverDownloader;
    private MockObject&EntityManagerInterface $entityManager;
    private MockObject&ManagerRegistry $managerRegistry;

    protected function setUp(): void
    {
        $this->comicSeriesRepository = $this->createMock(ComicSeriesRepository::class);
        $this->coverDownloader = $this->createMock(CoverDownloader::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->managerRegistry = $this->createMock(ManagerRegistry::class);
    }

    /**
     * Teste que la commande récupère après une fermeture de l'EntityManager.
     */
    public function testCommandRecoverAfterEntityManagerClosed(): void
    {
        $series1 = new ComicSeries();
        $series1->setTitle('Série 1');
        $series1->setCoverUrl('https://example.com/1.jpg');

        $series2 = new ComicSeries();
        $series2->setTitle('Série 2');
        $series2->setCoverUrl('https://example.com/2.jpg');

        $series2Refetched = new ComicSeries();
        $series2Refetched->setTitle('Série 2');
        $series2Refetched->setCoverUrl('https://example.com/2.jpg');

        $this->comicSeriesRepository->method('findWithExternalCoverOnly')
            ->willReturn([$series1, $series2]);

        $this->comicSeriesRepository->method('find')
            ->willReturn($series2Refetched);

        $this->entityManager->method('contains')->willReturn(true);

        $callCount = 0;
        $this->coverDownloader->method('downloadAndStore')
            ->willReturnCallback(static function () use (&$callCount): bool {
                ++$callCount;
                if (1 === $callCount) {
                    throw EntityManagerClosed::create();
                }

                return true;
            });

        $freshEntityManager = $this->createMock(EntityManagerInterface::class);
        $freshEntityManager->method('isOpen')->willReturn(true);
        $freshEntityManager->method('contains')->willReturn(false);
        $freshEntityManager->expects(self::atLeastOnce())->method('flush');

        $this->entityManager->method('isOpen')->willReturn(false);
        $this->managerRegistry->expects(self::atLeastOnce())
            ->method('resetManager')
            ->willReturn($freshEntityManager);

        $command = $this->createCommand();
        $tester = new CommandTester($command);
        $tester->execute(['--delay' => '0']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    private function createCommand(): DownloadCoversCommand
    {
        return new DownloadCoversCommand(
            $this->comicSeriesRepository,
            $this->coverDownloader,
            $this->entityManager,
            $this->managerRegistry,
        );
    }
}
