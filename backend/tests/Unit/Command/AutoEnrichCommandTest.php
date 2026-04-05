<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\AutoEnrichCommand;
use App\Entity\ComicSeries;
use App\Enum\ComicType;
use App\Enum\EnrichmentConfidence;
use App\Repository\ComicSeriesRepository;
use App\Service\Enrichment\EnrichmentService;
use App\Service\Lookup\Contract\LookupResult;
use App\Service\Lookup\LookupOrchestrator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\EntityManagerClosed;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests unitaires pour la commande d'enrichissement automatique.
 */
final class AutoEnrichCommandTest extends TestCase
{
    private MockObject&ComicSeriesRepository $comicSeriesRepository;
    private MockObject&EntityManagerInterface $entityManager;
    private MockObject&EnrichmentService $enrichmentService;
    private MockObject&LookupOrchestrator $lookupOrchestrator;
    private MockObject&ManagerRegistry $managerRegistry;

    protected function setUp(): void
    {
        $this->comicSeriesRepository = $this->createMock(ComicSeriesRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->enrichmentService = $this->createMock(EnrichmentService::class);
        $this->lookupOrchestrator = $this->createMock(LookupOrchestrator::class);
        $this->managerRegistry = $this->createMock(ManagerRegistry::class);
    }

    /**
     * Teste que la commande continue après une fermeture de l'EntityManager
     * et réinitialise l'EM via le ManagerRegistry.
     */
    public function testCommandRecoverAfterEntityManagerClosed(): void
    {
        $series1 = new ComicSeries();
        $series1->setTitle('Série qui ferme EM');
        $series1->setType(ComicType::MANGA);

        $series2 = new ComicSeries();
        $series2->setTitle('Série suivante');
        $series2->setType(ComicType::BD);

        // Série ré-attachée après reset de l'EM
        $series2Refetched = new ComicSeries();
        $series2Refetched->setTitle('Série suivante');
        $series2Refetched->setType(ComicType::BD);

        $this->comicSeriesRepository->method('findForAutoEnrich')
            ->willReturn([$series1, $series2]);

        // Après reset, find() retourne la série ré-attachée
        $this->comicSeriesRepository->method('find')
            ->willReturn($series2Refetched);

        // L'EM initial contient series1, mais pas series2 après reset
        $this->entityManager->method('contains')
            ->willReturn(true);

        // La première série déclenche une erreur qui ferme l'EM
        $this->lookupOrchestrator->method('lookupByTitle')
            ->willReturnCallback(static function (string $title) {
                if ('Série qui ferme EM' === $title) {
                    throw EntityManagerClosed::create();
                }

                return new LookupResult(description: 'Résultat', source: 'test');
            });

        $this->lookupOrchestrator->method('getLastSources')->willReturn(['test']);

        // Après la fermeture, le ManagerRegistry réinitialise l'EM
        $freshEntityManager = $this->createMock(EntityManagerInterface::class);
        $freshEntityManager->method('isOpen')->willReturn(true);
        // Le nouvel EM ne contient pas l'entité détachée → déclenche re-fetch
        $freshEntityManager->method('contains')->willReturn(false);

        $this->entityManager->method('isOpen')->willReturn(false);
        $this->managerRegistry->expects(self::atLeastOnce())
            ->method('resetManager')
            ->willReturn($freshEntityManager);

        $this->enrichmentService->expects(self::once())
            ->method('enrich')
            ->willReturn(EnrichmentConfidence::HIGH);

        $freshEntityManager->expects(self::atLeastOnce())->method('flush');

        $command = $this->createCommand();
        $tester = new CommandTester($command);
        $tester->execute(['--delay' => '0']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('erreur', $tester->getDisplay());
    }

    /**
     * Teste que le flush final utilise l'EM courant (potentiellement réinitialisé).
     */
    public function testFinalFlushUsesResetEntityManager(): void
    {
        $series = new ComicSeries();
        $series->setTitle('Test flush final');
        $series->setType(ComicType::BD);

        $this->comicSeriesRepository->method('findForAutoEnrich')
            ->willReturn([$series]);

        $this->lookupOrchestrator->method('lookupByTitle')
            ->willReturn(null);

        $this->entityManager->method('isOpen')->willReturn(true);
        $this->entityManager->method('contains')->willReturn(true);
        $this->entityManager->expects(self::once())->method('flush');

        $command = $this->createCommand();
        $tester = new CommandTester($command);
        $tester->execute(['--delay' => '0']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    private function createCommand(): AutoEnrichCommand
    {
        return new AutoEnrichCommand(
            $this->comicSeriesRepository,
            $this->entityManager,
            $this->enrichmentService,
            $this->lookupOrchestrator,
            $this->managerRegistry,
        );
    }
}
