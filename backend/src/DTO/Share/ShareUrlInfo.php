<?php

declare(strict_types=1);

namespace App\DTO\Share;

use App\Enum\ComicType;

final readonly class ShareUrlInfo
{
    public const string SOURCE_AMAZON = 'amazon';
    public const string SOURCE_BEDETHEQUE = 'bedetheque';
    public const string SOURCE_UNKNOWN = 'unknown';
    public const string SOURCE_WIKIPEDIA = 'wikipedia';

    public function __construct(
        public string $source,
        public string $originalUrl,
        public ?string $isbn = null,
        public ?string $titleHint = null,
        public ?ComicType $type = null,
    ) {
    }
}
