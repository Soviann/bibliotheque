<?php

declare(strict_types=1);

namespace App\Service\Lookup;

use App\Enum\ComicType;
use App\Message\EnrichSeriesMessage;
use App\Repository\ComicSeriesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Met en file l'enrichissement des séries avec métadonnées manquantes.
 *
 * Le travail est délégué au worker Messenger (un message par série) :
 * l'enrichissement, le scoring et le téléchargement des couvertures
 * s'exécutent en arrière-plan via EnrichSeriesHandler.
 */
final readonly class BatchLookupService
{
    public function __construct(
        private ComicSeriesRepository $comicSeriesRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * Compte les séries à traiter.
     */
    public function countSeriesToProcess(?ComicType $type = null, bool $force = false): int
    {
        return \count($this->comicSeriesRepository->findWithMissingLookupData(
            type: $type,
            force: $force,
        ));
    }

    /**
     * Dispatche un message d'enrichissement par série à traiter.
     *
     * En mode force, `lookupCompletedAt` est réinitialisé avant le dispatch
     * pour que le handler relance le lookup (le handler ignore les séries
     * déjà complétées). On évite ainsi d'ajouter un champ au message, qui
     * casserait la désérialisation entre versions du worker.
     *
     * @return int le nombre de séries mises en file
     */
    public function queue(?ComicType $type = null, bool $force = false, int $limit = 0): int
    {
        $seriesList = $this->comicSeriesRepository->findWithMissingLookupData(
            type: $type,
            limit: $limit > 0 ? $limit : null,
            force: $force,
        );

        if ($force) {
            foreach ($seriesList as $series) {
                $series->setLookupCompletedAt(null);
            }
            $this->entityManager->flush();
        }

        foreach ($seriesList as $series) {
            $id = $series->getId();

            if (null === $id) {
                continue;
            }

            $this->messageBus->dispatch(new EnrichSeriesMessage($id, 'batch'));
        }

        return \count($seriesList);
    }
}
