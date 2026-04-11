<?php

declare(strict_types=1);

namespace App\Service\Share;

use App\DTO\Share\ShareUrlInfo;
use App\Enum\ComicType;

final class ShareUrlParser
{
    public function parse(string $url): ShareUrlInfo
    {
        $parts = \parse_url($url);

        if (false === $parts || !isset($parts['host'])) {
            return new ShareUrlInfo(
                source: ShareUrlInfo::SOURCE_UNKNOWN,
                originalUrl: $url,
            );
        }

        $host = \strtolower($parts['host']);
        if (\str_starts_with($host, 'www.')) {
            $host = \substr($host, 4);
        }

        $path = $parts['path'] ?? '';

        if (\str_starts_with($host, 'amazon.')) {
            return $this->parseAmazon($url, $path);
        }

        if ('bedetheque.com' === $host) {
            return $this->parseBedetheque($url, $path);
        }

        if (\str_ends_with($host, '.wikipedia.org')) {
            return $this->parseWikipedia($url, $path);
        }

        return new ShareUrlInfo(
            source: ShareUrlInfo::SOURCE_UNKNOWN,
            originalUrl: $url,
        );
    }

    private function parseAmazon(string $url, string $path): ShareUrlInfo
    {
        if (1 === \preg_match('#/(?:dp|gp/product)/([A-Z0-9]{10})#i', $path, $matches)) {
            $asin = $matches[1];
            $isbn = $this->isIsbn10($asin) ? $asin : null;

            return new ShareUrlInfo(
                source: ShareUrlInfo::SOURCE_AMAZON,
                originalUrl: $url,
                isbn: $isbn,
            );
        }

        return new ShareUrlInfo(
            source: ShareUrlInfo::SOURCE_AMAZON,
            originalUrl: $url,
        );
    }

    private function parseBedetheque(string $url, string $path): ShareUrlInfo
    {
        if (
            1 === \preg_match('#/serie-\d+-([^/.]+)#', $path, $matches)
            || 1 === \preg_match('#/album-\d+-\d+-([^/.]+)#', $path, $matches)
        ) {
            return new ShareUrlInfo(
                source: ShareUrlInfo::SOURCE_BEDETHEQUE,
                originalUrl: $url,
                titleHint: $this->slugToTitle($matches[1]),
                type: ComicType::BD,
            );
        }

        return new ShareUrlInfo(
            source: ShareUrlInfo::SOURCE_BEDETHEQUE,
            originalUrl: $url,
            type: ComicType::BD,
        );
    }

    private function parseWikipedia(string $url, string $path): ShareUrlInfo
    {
        if (1 === \preg_match('|/wiki/([^?#]+)|', $path, $matches)) {
            $title = \urldecode($matches[1]);
            $title = \str_replace('_', ' ', $title);

            return new ShareUrlInfo(
                source: ShareUrlInfo::SOURCE_WIKIPEDIA,
                originalUrl: $url,
                titleHint: \trim($title),
            );
        }

        return new ShareUrlInfo(
            source: ShareUrlInfo::SOURCE_WIKIPEDIA,
            originalUrl: $url,
        );
    }

    private function slugToTitle(string $slug): string
    {
        $decoded = \urldecode($slug);
        $title = \str_replace(['-', '_'], ' ', $decoded);

        return \trim($title);
    }

    private function isIsbn10(string $asin): bool
    {
        return 1 === \preg_match('/^\d{9}[\dX]$/', $asin);
    }
}
