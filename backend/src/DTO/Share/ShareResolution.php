<?php

declare(strict_types=1);

namespace App\DTO\Share;

use App\Service\Lookup\Contract\LookupResult;

/**
 * Résultat de la résolution d'un partage d'URL.
 */
final readonly class ShareResolution
{
    public function __construct(
        public bool $matched,
        public ?int $seriesId = null,
        public ?LookupResult $lookupResult = null,
    ) {
    }

    /**
     * La série a été trouvée en base de données.
     */
    public static function matched(int $seriesId, LookupResult $result): self
    {
        return new self(
            matched: true,
            seriesId: $seriesId,
            lookupResult: $result,
        );
    }

    /**
     * Les données ont été trouvées via lookup, mais aucune série ne correspond en base.
     */
    public static function unmatched(LookupResult $result): self
    {
        return new self(
            matched: false,
            lookupResult: $result,
        );
    }

    /**
     * Aucune donnée exploitable (URL inconnue ou lookup vide).
     */
    public static function empty(): self
    {
        return new self(matched: false);
    }
}
