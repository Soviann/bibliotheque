<?php

declare(strict_types=1);

namespace App\Service\Enrichment;

use App\Enum\ComicType;
use App\Enum\EnrichmentConfidence;
use App\Enum\LookupMode;
use App\Service\Lookup\LookupResult;

/**
 * Calcule un score de confiance (0-1) à partir du contexte de requête et du résultat.
 */
final class ConfidenceScorer
{
    /**
     * @param list<string> $sources Providers ayant contribué au résultat
     */
    public function score(
        string $query,
        ?ComicType $queryType,
        LookupMode $mode,
        LookupResult $result,
        array $sources,
    ): EnrichmentConfidence {
        $score = $this->computeRawScore($query, $queryType, $mode, $result, $sources);

        return EnrichmentConfidence::fromScore($score);
    }

    /**
     * @param list<string> $sources
     */
    private function computeRawScore(
        string $query,
        ?ComicType $queryType,
        LookupMode $mode,
        LookupResult $result,
        array $sources,
    ): float {
        // ISBN exact match — très haute confiance
        if (LookupMode::ISBN === $mode && null !== $result->isbn) {
            $normalizedQuery = \preg_replace('/[\s-]/', '', $query) ?? '';
            $normalizedIsbn = \preg_replace('/[\s-]/', '', $result->isbn) ?? '';

            if ($normalizedQuery === $normalizedIsbn) {
                return 0.95;
            }
        }

        $score = 0.0;

        // Similarité du titre (poids principal : max 0.60)
        if (null !== $result->title) {
            \similar_text(
                \mb_strtolower($query),
                \mb_strtolower($result->title),
                $percent,
            );
            $score += ($percent / 100) * 0.60;
        }

        // Nombre de providers en accord (max 0.20)
        $providerBonus = \min(\count($sources), 3) / 3 * 0.20;
        $score += $providerBonus;

        // Correspondance de type (0.10)
        if (null !== $queryType) {
            $score += 0.10;
        }

        // Présence d'auteurs — signal de qualité (0.10)
        if (null !== $result->authors && '' !== $result->authors) {
            $score += 0.10;
        }

        return \min($score, 1.0);
    }
}
