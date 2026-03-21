<?php

declare(strict_types=1);

namespace App\Service\Merge;

use App\DTO\MergeGroup;
use App\DTO\MergePreview;
use App\DTO\MergePreviewTome;
use App\Entity\ComicSeries;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
use App\Service\Lookup\Gemini\GeminiClientPool;
use Gemini\Data\GoogleSearch;
use Gemini\Data\Tool;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Construit un aperçu de fusion à partir d'un groupe détecté ou d'une sélection manuelle.
 */
final readonly class MergePreviewBuilder
{
    public function __construct(
        private GeminiClientPool $geminiClientPool,
        #[Autowire(service: 'limiter.gemini_api')]
        private RateLimiterFactoryInterface $limiterFactory,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Construit un aperçu depuis un groupe détecté automatiquement.
     *
     * @param array<int, ComicSeries> $seriesMap Séries indexées par ID
     */
    public function buildFromGroup(MergeGroup $group, array $seriesMap): MergePreview
    {
        /** @var array<int, ?int> $tomeNumberMap ID série → numéro de tome suggéré */
        $tomeNumberMap = [];
        foreach ($group->entries as $entry) {
            $tomeNumberMap[$entry->seriesId] = $entry->suggestedTomeNumber;
        }

        $seriesList = [];
        foreach ($group->entries as $entry) {
            if (isset($seriesMap[$entry->seriesId])) {
                $seriesList[] = $seriesMap[$entry->seriesId];
            }
        }

        return $this->buildPreview($group->suggestedTitle, $seriesList, $tomeNumberMap);
    }

    /**
     * Construit un aperçu depuis une sélection manuelle (titre 1ère série, numéros séquentiels).
     *
     * @param ComicSeries[] $seriesList
     */
    public function buildFromManualSelection(array $seriesList): MergePreview
    {
        $title = $seriesList[0]->getTitle();
        /** @var array<int, ?int> $tomeNumberMap */
        $tomeNumberMap = [];
        $number = 1;
        foreach ($seriesList as $series) {
            $tomeNumberMap[(int) $series->getId()] = $number++;
        }

        return $this->buildPreview($title, $seriesList, $tomeNumberMap);
    }

    /**
     * Appelle Gemini pour suggérer un titre canonique et des numéros de tomes.
     *
     * @param ComicSeries[] $seriesList
     *
     * @return array{title: string, entries: list<array{id: int, tomeNumber: int|null}>}|null
     */
    public function suggestFromGemini(array $seriesList): ?array
    {
        return $this->callGeminiForManualSelection($seriesList);
    }

    /**
     * Construit l'aperçu commun à partir du titre, des séries et de la map de numéros.
     *
     * @param ComicSeries[]    $seriesList
     * @param array<int, ?int> $tomeNumberMap ID série → numéro de tome suggéré
     */
    private function buildPreview(string $title, array $seriesList, array $tomeNumberMap): MergePreview
    {
        $primary = [] !== $seriesList ? $seriesList[0] : null;

        return new MergePreview(
            amazonUrl: $this->reconcileFirstNonNull($seriesList, static fn (ComicSeries $s): ?string => $s->getAmazonUrl()),
            authors: $this->reconcileAuthors($seriesList),
            coverUrl: $this->reconcileCoverUrl($seriesList),
            defaultTomeBought: false,
            defaultTomeDownloaded: false,
            defaultTomeRead: false,
            description: $this->reconcileDescription($seriesList),
            isOneShot: false,
            latestPublishedIssue: $this->reconcileLatestPublishedIssue($seriesList),
            latestPublishedIssueComplete: $this->reconcileLatestPublishedIssueComplete($seriesList),
            notInterestedBuy: false,
            notInterestedNas: false,
            publishedDate: $this->reconcileFirstNonNull($seriesList, static fn (ComicSeries $s): ?string => $s->getPublishedDate()),
            publisher: $this->reconcilePublisher($seriesList),
            sourceSeriesIds: \array_values(\array_map(
                static fn (ComicSeries $s): int => (int) $s->getId(),
                $seriesList,
            )),
            status: null !== $primary ? $primary->getStatus()->value : ComicStatus::BUYING->value,
            title: $title,
            tomes: $this->buildTomes($seriesList, $tomeNumberMap),
            type: null !== $primary ? $primary->getType()->value : ComicType::BD->value,
        );
    }

    /**
     * Union des noms d'auteurs, dédupliqués (case-insensitive, conserve la première casse rencontrée).
     *
     * @param ComicSeries[] $seriesList
     *
     * @return list<string>
     */
    private function reconcileAuthors(array $seriesList): array
    {
        /** @var array<string, string> $seen clé lowercase → nom original */
        $seen = [];

        foreach ($seriesList as $series) {
            foreach ($series->getAuthors() as $author) {
                $lower = \mb_strtolower($author->getName());
                if (!isset($seen[$lower])) {
                    $seen[$lower] = $author->getName();
                }
            }
        }

        return \array_values($seen);
    }

    /**
     * Premier coverUrl non null.
     *
     * @param ComicSeries[] $seriesList
     */
    private function reconcileCoverUrl(array $seriesList): ?string
    {
        foreach ($seriesList as $series) {
            if (null !== $series->getCoverUrl()) {
                return $series->getCoverUrl();
            }
        }

        return null;
    }

    /**
     * Description la plus longue.
     *
     * @param ComicSeries[] $seriesList
     */
    private function reconcileDescription(array $seriesList): ?string
    {
        $longest = null;
        $longestLen = 0;

        foreach ($seriesList as $series) {
            $desc = $series->getDescription();
            if (null !== $desc && \mb_strlen($desc) > $longestLen) {
                $longest = $desc;
                $longestLen = \mb_strlen($desc);
            }
        }

        return $longest;
    }

    /**
     * Max de latestPublishedIssue.
     *
     * @param ComicSeries[] $seriesList
     */
    private function reconcileLatestPublishedIssue(array $seriesList): ?int
    {
        $max = null;

        foreach ($seriesList as $series) {
            $val = $series->getLatestPublishedIssue();
            if (null !== $val && (null === $max || $val > $max)) {
                $max = $val;
            }
        }

        return $max;
    }

    /**
     * True si au moins une source est complète.
     *
     * @param ComicSeries[] $seriesList
     */
    private function reconcileLatestPublishedIssueComplete(array $seriesList): bool
    {
        foreach ($seriesList as $series) {
            if ($series->isLatestPublishedIssueComplete()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Première valeur non null extraite par le callback.
     *
     * @template T
     *
     * @param ComicSeries[]             $seriesList
     * @param callable(ComicSeries): ?T $extractor
     *
     * @return ?T
     */
    private function reconcileFirstNonNull(array $seriesList, callable $extractor): mixed
    {
        foreach ($seriesList as $series) {
            $value = $extractor($series);
            if (null !== $value) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Premier publisher non null.
     *
     * @param ComicSeries[] $seriesList
     */
    private function reconcilePublisher(array $seriesList): ?string
    {
        foreach ($seriesList as $series) {
            if (null !== $series->getPublisher()) {
                return $series->getPublisher();
            }
        }

        return null;
    }

    /**
     * Construit la liste de tomes fusionnée, triée par numéro.
     *
     * @param ComicSeries[]    $seriesList
     * @param array<int, ?int> $tomeNumberMap ID série → numéro de tome suggéré
     *
     * @return list<MergePreviewTome>
     */
    private function buildTomes(array $seriesList, array $tomeNumberMap): array
    {
        $tomes = [];

        foreach ($seriesList as $series) {
            $seriesId = (int) $series->getId();
            $seriesTomes = $series->getTomes()->toArray();
            $isOneShotOrSingle = $series->isOneShot() || 1 === \count($seriesTomes);
            $suggestedNumber = $tomeNumberMap[$seriesId] ?? null;

            foreach ($seriesTomes as $tome) {
                if ($isOneShotOrSingle && null !== $suggestedNumber) {
                    $number = $suggestedNumber;
                    $title = $series->getTitle();
                } else {
                    $number = $tome->getNumber();
                    $title = $tome->getTitle();
                }

                $tomes[] = new MergePreviewTome(
                    bought: $tome->isBought(),
                    downloaded: $tome->isDownloaded(),
                    isbn: $tome->getIsbn(),
                    number: $number,
                    onNas: $tome->isOnNas(),
                    read: $tome->isRead(),
                    title: $title,
                    tomeEnd: $tome->getTomeEnd(),
                );
            }
        }

        // Tri par numéro
        \usort($tomes, static fn (MergePreviewTome $a, MergePreviewTome $b): int => $a->number <=> $b->number);

        return $tomes;
    }

    /**
     * Appelle Gemini pour obtenir le titre canonique et les numéros de tomes.
     *
     * @param ComicSeries[] $seriesList
     *
     * @return array{title: string, entries: list<array{id: int, tomeNumber: ?int}>}|null
     */
    private function callGeminiForManualSelection(array $seriesList): ?array
    {
        $limiter = $this->limiterFactory->create('gemini_global');
        if (!$limiter->consume()->isAccepted()) {
            $this->logger->warning('Rate limit atteint pour la construction d\'aperçu de fusion.');

            return null;
        }

        $prompt = $this->buildManualSelectionPrompt($seriesList);

        try {
            $text = $this->geminiClientPool->executeWithRetry(static function ($client, \BackedEnum|string $model) use ($prompt): string {
                $response = $client
                    ->generativeModel(model: $model)
                    ->withTool(new Tool(googleSearch: GoogleSearch::from()))
                    ->generateContent($prompt);

                return $response->text();
            });
        } catch (\Throwable $e) {
            $this->logger->error('Erreur Gemini lors de la construction d\'aperçu de fusion : {message}', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        return $this->parseGeminiResponse($text);
    }

    /**
     * Construit le prompt Gemini pour la sélection manuelle.
     *
     * @param ComicSeries[] $seriesList
     */
    private function buildManualSelectionPrompt(array $seriesList): string
    {
        $lines = [];
        foreach ($seriesList as $series) {
            $lines[] = \sprintf('- %s (ID: %d)', $series->getTitle(), $series->getId());
        }

        $titlesList = \implode("\n", $lines);

        return <<<PROMPT
            Tu es un expert en bandes dessinées, comics et mangas.
            Voici des séries de ma bibliothèque que je souhaite fusionner en une seule série.
            Suggère un nom canonique pour la série fusionnée et un numéro de tome pour chaque entrée.

            Réponds UNIQUEMENT en JSON valide :
            {"title": "Nom de la série", "entries": [{"id": <ID>, "tomeNumber": <numéro ou null>}]}

            Séries :
            {$titlesList}
            PROMPT;
    }

    /**
     * Parse la réponse JSON de Gemini.
     *
     * @return array{title: string, entries: list<array{id: int, tomeNumber: int|null}>}|null
     */
    private function parseGeminiResponse(string $text): ?array
    {
        $cleaned = \preg_replace('/^```(?:json)?\s*\n?(.*?)\n?```$/s', '$1', \trim($text));
        $data = \json_decode($cleaned ?? $text, true);

        if (!\is_array($data) || !isset($data['title']) || !\is_string($data['title'])) {
            return null;
        }

        if (!isset($data['entries']) || !\is_array($data['entries'])) {
            return null;
        }

        /** @var list<array{id: int, tomeNumber: int|null}> $entries */
        $entries = [];
        foreach ($data['entries'] as $entry) {
            if (!\is_array($entry) || !isset($entry['id']) || !\is_numeric($entry['id'])) {
                continue;
            }
            $entries[] = [
                'id' => (int) $entry['id'],
                'tomeNumber' => isset($entry['tomeNumber']) && \is_numeric($entry['tomeNumber']) ? (int) $entry['tomeNumber'] : null,
            ];
        }

        return ['entries' => $entries, 'title' => $data['title']];
    }
}
