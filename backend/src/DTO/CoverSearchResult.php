<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Résultat d'une recherche de couverture via Google Custom Search.
 */
final readonly class CoverSearchResult implements \JsonSerializable
{
    public function __construct(
        public int $height,
        public string $thumbnail,
        public string $title,
        public string $url,
        public int $width,
    ) {
    }

    /**
     * @return array{height: int, thumbnail: string, title: string, url: string, width: int}
     */
    public function jsonSerialize(): array
    {
        return [
            'height' => $this->height,
            'thumbnail' => $this->thumbnail,
            'title' => $this->title,
            'url' => $this->url,
            'width' => $this->width,
        ];
    }
}
