<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\ComicStatus;
use App\Enum\ComicType;

/**
 * DTO pour les filtres de recherche des séries.
 * Utilisé avec #[MapQueryString] dans les contrôleurs.
 *
 * Note: type et status sont des strings pour permettre aux valeurs invalides
 * d'être ignorées (comportement de tryFrom) plutôt que de lever une exception.
 */
class ComicFilters
{
    public function __construct(
        public ?string $nas = null,
        public ?string $q = null,
        public string $sort = 'title_asc',
        public ?string $status = null,
        public ?string $type = null,
    ) {
    }

    /**
     * Retourne la valeur booléenne du filtre NAS pour le repository.
     */
    public function getOnNas(): ?bool
    {
        return match ($this->nas) {
            '1' => true,
            '0' => false,
            default => null,
        };
    }

    /**
     * Retourne la valeur de recherche (null si vide).
     */
    public function getSearch(): ?string
    {
        return '' !== $this->q && null !== $this->q ? $this->q : null;
    }

    /**
     * Retourne l'enum ComicStatus (null si invalide ou non défini).
     */
    public function getStatus(): ?ComicStatus
    {
        return null !== $this->status ? ComicStatus::tryFrom($this->status) : null;
    }

    /**
     * Retourne l'enum ComicType (null si invalide ou non défini).
     */
    public function getType(): ?ComicType
    {
        return null !== $this->type ? ComicType::tryFrom($this->type) : null;
    }
}
