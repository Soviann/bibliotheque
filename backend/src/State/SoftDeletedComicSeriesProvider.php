<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\ComicSeries;
use App\Repository\ComicSeriesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provider qui désactive le filtre soft-delete pour accéder aux séries supprimées.
 *
 * Utilisé par les opérations restore et permanent-delete.
 *
 * @implements ProviderInterface<ComicSeries>
 */
final readonly class SoftDeletedComicSeriesProvider implements ProviderInterface
{
    public function __construct(
        private ComicSeriesRepository $comicSeriesRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ComicSeries
    {
        $this->entityManager->getFilters()->disable('soft_delete');

        try {
            $comic = $this->comicSeriesRepository->find($uriVariables['id'] ?? 0);
        } finally {
            $this->entityManager->getFilters()->enable('soft_delete');
        }

        if (!$comic instanceof ComicSeries) {
            throw new NotFoundHttpException('Série non trouvée.');
        }

        if (!$comic->isDeleted()) {
            throw new NotFoundHttpException();
        }

        return $comic;
    }
}
