<?php

declare(strict_types=1);

namespace App\Service\Share;

use App\DTO\Share\ShareResolution;
use App\DTO\Share\ShareUrlInfo;
use App\Entity\ComicSeries;
use App\Enum\ComicType;
use App\Message\EnrichSeriesMessage;
use App\Repository\ComicSeriesRepository;
use App\Service\Lookup\Contract\LookupResult;
use App\Service\Lookup\LookupOrchestrator;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Orchestre la résolution d'un lien partagé :
 * parsing → lookup → match base → enrichissement.
 */
final readonly class ShareResolver
{
    public function __construct(
        private ComicSeriesRepository $comicSeriesRepository,
        private LookupOrchestrator $lookupOrchestrator,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * Résout un lien partagé en cherchant la série correspondante ou en retournant les données lookup.
     */
    public function resolve(ShareUrlInfo $info, ?string $titleFallback = null): ShareResolution
    {
        $result = null;

        if (null !== $info->isbn) {
            $result = $this->lookupOrchestrator->lookup($info->isbn, $info->type);
        } elseif (null !== $info->titleHint) {
            $result = $this->lookupOrchestrator->lookupByTitle($info->titleHint, $info->type);
        } elseif (null !== $titleFallback) {
            $result = $this->lookupOrchestrator->lookupByTitle($titleFallback);
        } else {
            return ShareResolution::empty();
        }

        if (!$result instanceof LookupResult) {
            return ShareResolution::empty();
        }

        $series = null;

        if (null !== $result->isbn) {
            $series = $this->comicSeriesRepository->findOneByTomeIsbn($result->isbn);
        }

        if (!$series instanceof ComicSeries && null !== $result->title) {
            $series = $this->comicSeriesRepository->findOneByFuzzyTitle(
                $result->title,
                $info->type ?? ComicType::BD,
            );
        }

        if (!$series instanceof ComicSeries && null !== $result->title) {
            $series = $this->comicSeriesRepository->findOneByFuzzyTitleAnyType($result->title);
        }

        if ($series instanceof ComicSeries) {
            $seriesId = $series->getId();

            if (null !== $seriesId) {
                $this->messageBus->dispatch(new EnrichSeriesMessage($seriesId, 'event:share'));

                return ShareResolution::matched($seriesId, $result);
            }
        }

        return ShareResolution::unmatched($result);
    }
}
