<?php

declare(strict_types=1);

namespace App\Service\Enrichment;

use App\Entity\ComicSeries;
use App\Entity\EnrichmentLog;
use App\Entity\EnrichmentProposal;
use App\Enum\EnrichableField;
use App\Enum\EnrichmentAction;
use App\Enum\EnrichmentConfidence;
use App\Enum\LookupMode;
use App\Repository\EnrichmentProposalRepository;
use App\Service\Lookup\Contract\LookupResult;
use App\Service\Lookup\LookupApplier;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Orchestre l'enrichissement : score → auto-apply / proposition / skip.
 */
class EnrichmentService
{
    /**
     * Correspondance entre les noms de champs retournés par LookupApplier
     * et les noms de propriétés de LookupResult → EnrichableField.
     *
     * @var array<string, EnrichableField>
     */
    private const array APPLIER_FIELD_MAP = [
        'amazonUrl' => EnrichableField::AMAZON_URL,
        'authors' => EnrichableField::AUTHORS,
        'coverUrl' => EnrichableField::COVER,
        'description' => EnrichableField::DESCRIPTION,
        'isOneShot' => EnrichableField::IS_ONE_SHOT,
        'latestPublishedIssue' => EnrichableField::LATEST_PUBLISHED_ISSUE,
        'publisher' => EnrichableField::PUBLISHER,
    ];

    /**
     * Correspondance entre les propriétés de LookupResult et EnrichableField.
     *
     * @var array<string, EnrichableField>
     */
    private const array RESULT_FIELD_MAP = [
        'amazonUrl' => EnrichableField::AMAZON_URL,
        'authors' => EnrichableField::AUTHORS,
        'description' => EnrichableField::DESCRIPTION,
        'isbn' => EnrichableField::ISBN,
        'isOneShot' => EnrichableField::IS_ONE_SHOT,
        'latestPublishedIssue' => EnrichableField::LATEST_PUBLISHED_ISSUE,
        'publisher' => EnrichableField::PUBLISHER,
        'thumbnail' => EnrichableField::COVER,
    ];

    public function __construct(
        private readonly ConfidenceScorer $confidenceScorer,
        private readonly EntityManagerInterface $entityManager,
        private readonly LookupApplier $lookupApplier,
        private readonly LoggerInterface $logger,
        private readonly EnrichmentProposalRepository $proposalRepository,
    ) {
    }

    /**
     * Enrichit une série à partir d'un résultat de lookup.
     *
     * @param list<string> $sources Providers ayant contribué
     */
    public function enrich(
        ComicSeries $series,
        LookupResult $result,
        LookupMode $mode,
        array $sources,
    ): EnrichmentConfidence {
        $confidence = $this->confidenceScorer->score(
            $series->getTitle(),
            $series->getType(),
            $mode,
            $result,
            $sources,
        );

        match ($confidence) {
            EnrichmentConfidence::HIGH => $this->autoApply($series, $result, $confidence, $sources),
            EnrichmentConfidence::MEDIUM => $this->createProposals($series, $result, $confidence, $sources),
            EnrichmentConfidence::LOW => $this->logSkip($series, $result, $confidence, $sources),
        };

        return $confidence;
    }

    /**
     * Retourne la valeur actuelle d'un champ enrichissable sur une série.
     */
    public function getSeriesValue(ComicSeries $series, EnrichableField $field): mixed
    {
        return match ($field) {
            EnrichableField::AMAZON_URL => $series->getAmazonUrl(),
            EnrichableField::AUTHORS => $series->getAuthors()->isEmpty() ? null : \implode(', ', $series->getAuthors()->map(static fn ($a) => $a->getName())->toArray()),
            EnrichableField::COVER => $series->getCoverUrl(),
            EnrichableField::DESCRIPTION => $series->getDescription(),
            EnrichableField::ISBN => null,
            EnrichableField::IS_ONE_SHOT => $series->isOneShot(),
            EnrichableField::LATEST_PUBLISHED_ISSUE => $series->getLatestPublishedIssue(),
            EnrichableField::PUBLISHER => $series->getPublisher(),
        };
    }

    /**
     * @param list<string> $sources
     */
    private function autoApply(
        ComicSeries $series,
        LookupResult $result,
        EnrichmentConfidence $confidence,
        array $sources,
    ): void {
        $source = \implode(', ', $sources);

        // Capturer les anciennes valeurs AVANT l'application
        $oldValues = [];

        foreach (self::APPLIER_FIELD_MAP as $applierField => $enrichableField) {
            $oldValues[$applierField] = $this->getSeriesValue($series, $enrichableField);
        }

        $updatedFields = $this->lookupApplier->apply($series, $result);

        foreach ($updatedFields as $fieldName) {
            $enrichableField = self::APPLIER_FIELD_MAP[$fieldName] ?? null;

            if (null === $enrichableField) {
                continue;
            }

            $newValue = $this->getSeriesValue($series, $enrichableField);

            $log = new EnrichmentLog(
                action: EnrichmentAction::AUTO_APPLIED,
                comicSeries: $series,
                confidence: $confidence,
                field: $enrichableField,
                newValue: $newValue,
                oldValue: $oldValues[$fieldName] ?? null,
                source: $source,
            );
            $this->entityManager->persist($log);
        }

        $this->logger->info('Enrichissement auto-appliqué pour "{title}" : {fields}', [
            'fields' => \implode(', ', $updatedFields),
            'title' => $series->getTitle(),
        ]);
    }

    /**
     * @param list<string> $sources
     */
    private function createProposals(
        ComicSeries $series,
        LookupResult $result,
        EnrichmentConfidence $confidence,
        array $sources,
    ): void {
        $source = \implode(', ', $sources);

        foreach (self::RESULT_FIELD_MAP as $resultField => $enrichableField) {
            $proposedValue = $this->getResultValue($result, $resultField);

            if (null === $proposedValue) {
                continue;
            }

            $currentValue = $this->getSeriesValue($series, $enrichableField);

            // Ne propose pas si la valeur est déjà identique
            if ($currentValue === $proposedValue) {
                continue;
            }

            // Vérifie qu'il n'y a pas déjà une proposition en attente
            if (null !== $this->proposalRepository->findPendingBySeriesAndField($series, $enrichableField)) {
                continue;
            }

            $proposal = new EnrichmentProposal(
                comicSeries: $series,
                confidence: $confidence,
                currentValue: $currentValue,
                field: $enrichableField,
                proposedValue: $proposedValue,
                source: $source,
            );
            $this->entityManager->persist($proposal);
        }
    }

    /**
     * @param list<string> $sources
     */
    private function logSkip(
        ComicSeries $series,
        LookupResult $result,
        EnrichmentConfidence $confidence,
        array $sources,
    ): void {
        $source = \implode(', ', $sources);

        // Log un skip par champ non-null du résultat
        $logged = false;

        foreach (self::RESULT_FIELD_MAP as $resultField => $enrichableField) {
            $proposedValue = $this->getResultValue($result, $resultField);

            if (null === $proposedValue) {
                continue;
            }

            $log = new EnrichmentLog(
                action: EnrichmentAction::SKIPPED,
                comicSeries: $series,
                confidence: $confidence,
                field: $enrichableField,
                newValue: $proposedValue,
                oldValue: $this->getSeriesValue($series, $enrichableField),
                source: $source,
            );
            $this->entityManager->persist($log);
            $logged = true;
        }

        if (!$logged) {
            $this->logger->info('Enrichissement ignoré pour "{title}" — résultat vide', [
                'title' => $series->getTitle(),
            ]);

            return;
        }

        $this->logger->info('Enrichissement ignoré pour "{title}" — confiance trop basse', [
            'title' => $series->getTitle(),
        ]);
    }

    private function getResultValue(LookupResult $result, string $fieldName): mixed
    {
        return match ($fieldName) {
            'amazonUrl' => $result->amazonUrl,
            'authors' => $result->authors,
            'description' => $result->description,
            'isbn' => $result->isbn,
            'isOneShot' => $result->isOneShot,
            'latestPublishedIssue' => $result->latestPublishedIssue,
            'publisher' => $result->publisher,
            'thumbnail' => $result->thumbnail,
            default => null,
        };
    }
}
