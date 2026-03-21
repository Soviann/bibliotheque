<?php

declare(strict_types=1);

namespace App\Service\Merge;

use App\DTO\MergeGroup;
use App\DTO\MergeGroupEntry;
use App\Entity\ComicSeries;
use App\Service\Lookup\Gemini\GeminiClientPool;
use App\Service\Lookup\Gemini\GeminiJsonParser;
use Gemini\Data\GoogleSearch;
use Gemini\Data\Tool;
use Gemini\Exceptions\ErrorException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Détecte les groupes de séries qui devraient être fusionnées via Gemini.
 */
final readonly class SeriesGroupDetector
{
    private const int MAX_BATCH_SIZE = 50;

    public function __construct(
        private GeminiClientPool $geminiClientPool,
        #[Autowire(service: 'limiter.gemini_api')]
        private RateLimiterFactoryInterface $limiterFactory,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Détecte les groupes de séries qui devraient être fusionnées.
     *
     * @param ComicSeries[] $seriesList
     *
     * @return list<MergeGroup>
     *
     * @throws \RuntimeException si le rate limit est atteint
     */
    public function detect(array $seriesList): array
    {
        $batches = $this->buildBatches($seriesList);
        $allGroups = [];
        $processedCount = 0;

        foreach ($batches as $batch) {
            $limiter = $this->limiterFactory->create('gemini_global');
            $limit = $limiter->consume();
            if (!$limit->isAccepted()) {
                $this->logger->warning('Rate limit atteint après {count} batches sur {total}.', [
                    'count' => $processedCount,
                    'total' => \count($batches),
                ]);
                break;
            }

            $groups = $this->processBatch($batch);
            \array_push($allGroups, ...$groups);
            ++$processedCount;
        }

        if (0 === $processedCount) {
            throw new \RuntimeException('Rate limit atteint. Réessayez dans quelques minutes.');
        }

        return $allGroups;
    }

    /**
     * Regroupe les séries par première lettre (alphabétique).
     * Les titres commençant par un chiffre sont regroupés ensemble.
     * Les batches dépassant MAX_BATCH_SIZE sont découpés.
     *
     * @param ComicSeries[] $seriesList
     *
     * @return list<list<ComicSeries>>
     */
    private function buildBatches(array $seriesList): array
    {
        $groups = [];

        foreach ($seriesList as $series) {
            $firstChar = \mb_strtoupper(\mb_substr($series->getTitle(), 0, 1));
            $key = \is_numeric($firstChar) ? '0-9' : $firstChar;
            $groups[$key][] = $series;
        }

        \ksort($groups);

        $batches = [];
        foreach ($groups as $group) {
            if (\count($group) <= self::MAX_BATCH_SIZE) {
                $batches[] = $group;
            } else {
                foreach (\array_chunk($group, self::MAX_BATCH_SIZE) as $chunk) {
                    $batches[] = $chunk;
                }
            }
        }

        return $batches;
    }

    /**
     * Traite un batch de séries : appel Gemini + parsing de la réponse.
     *
     * @param ComicSeries[] $batch
     *
     * @return list<MergeGroup>
     */
    private function processBatch(array $batch): array
    {
        /** @var array<int, ComicSeries> $seriesMap */
        $seriesMap = [];
        foreach ($batch as $series) {
            $seriesMap[(int) $series->getId()] = $series;
        }

        $prompt = $this->buildPrompt($batch);

        try {
            $text = $this->geminiClientPool->executeWithRetry(static function ($client, \BackedEnum|string $model) use ($prompt): string {
                $response = $client
                    ->generativeModel(model: $model)
                    ->withTool(new Tool(googleSearch: GoogleSearch::from()))
                    ->generateContent($prompt);

                return $response->text();
            });
        } catch (\Throwable $e) {
            $this->logger->error('Erreur Gemini lors de la détection de groupes : {message}', [
                'message' => $e->getMessage(),
            ]);

            $isQuotaError = $e instanceof ErrorException
                ? 429 === $e->getErrorCode()
                : \str_contains($e->getMessage(), 'quota') || \str_contains($e->getMessage(), '429');

            if ($isQuotaError) {
                throw new \RuntimeException('Quota Gemini épuisé (toutes les clés). Réessayez dans quelques minutes.', $e->getCode(), $e);
            }

            return [];
        }

        $data = GeminiJsonParser::parseJsonFromText($text);
        if (null === $data) {
            $this->logger->warning('Réponse Gemini non parseable pour la détection de groupes.', [
                'response' => $text,
            ]);

            return [];
        }

        return $this->buildMergeGroups($data, $seriesMap);
    }

    /**
     * Construit le prompt Gemini pour un batch de séries.
     *
     * @param ComicSeries[] $batch
     */
    private function buildPrompt(array $batch): string
    {
        $lines = [];
        foreach ($batch as $series) {
            $lines[] = \sprintf('- %s (ID: %d)', $series->getTitle(), $series->getId());
        }

        $titlesList = \implode("\n", $lines);

        return <<<PROMPT
            Tu es un expert en bandes dessinées, comics et mangas.
            Voici une liste de titres de séries de ma bibliothèque. Certains sont en réalité des tomes d'une même série (par exemple "Astérix - Astérix chez les bretons" est un tome de la série "Astérix").

            Identifie les groupes de titres qui appartiennent à la même série.

            Pour chaque groupe, indique :
            - "title": le nom canonique de la série
            - "entries": un tableau avec pour chaque titre : "id" (l'ID fourni), "tomeNumber" (le numéro du tome dans la série, ou null si inconnu)

            Ne crée PAS de groupe pour les titres qui sont des séries indépendantes.
            Réponds UNIQUEMENT en JSON valide, sous la forme d'un tableau de groupes.

            Titres :
            {$titlesList}
            PROMPT;
    }

    /**
     * Construit les MergeGroup à partir des données JSON parsées.
     *
     * @param array<mixed>            $data
     * @param array<int, ComicSeries> $seriesMap
     *
     * @return list<MergeGroup>
     */
    private function buildMergeGroups(array $data, array $seriesMap): array
    {
        $groups = [];

        foreach ($data as $groupData) {
            if (!\is_array($groupData)) {
                continue;
            }

            /** @var array<string, mixed> $groupData */
            if (!isset($groupData['title']) || !\is_string($groupData['title'])) {
                continue;
            }

            if (!isset($groupData['entries']) || !\is_array($groupData['entries'])) {
                continue;
            }

            $entries = [];
            foreach ($groupData['entries'] as $entryData) {
                if (!\is_array($entryData)) {
                    continue;
                }

                /** @var array<string, mixed> $entryData */
                if (!isset($entryData['id']) || !\is_numeric($entryData['id'])) {
                    continue;
                }

                $seriesId = (int) $entryData['id'];
                $series = $seriesMap[$seriesId] ?? null;
                if (null === $series) {
                    continue;
                }

                $tomeNumber = $entryData['tomeNumber'] ?? null;

                $entries[] = new MergeGroupEntry(
                    originalTitle: $series->getTitle(),
                    seriesId: $seriesId,
                    suggestedTomeNumber: \is_numeric($tomeNumber) ? (int) $tomeNumber : null,
                );
            }

            // Filtrer les groupes avec moins de 2 entrées
            if (\count($entries) < 2) {
                continue;
            }

            $groups[] = new MergeGroup(
                entries: $entries,
                suggestedTitle: $groupData['title'],
            );
        }

        return $groups;
    }
}
