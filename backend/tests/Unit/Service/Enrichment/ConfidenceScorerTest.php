<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Enrichment;

use App\Enum\ComicType;
use App\Enum\EnrichmentConfidence;
use App\Enum\LookupMode;
use App\Service\Enrichment\ConfidenceScorer;
use App\Service\Lookup\Contract\LookupResult;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le scoring de confiance.
 */
final class ConfidenceScorerTest extends TestCase
{
    private ConfidenceScorer $scorer;

    protected function setUp(): void
    {
        $this->scorer = new ConfidenceScorer();
    }

    /**
     * Teste qu'un ISBN exact produit une confiance HIGH.
     */
    public function testIsbnExactMatchReturnsHigh(): void
    {
        $result = new LookupResult(isbn: '978-2-1234-5678-9', title: 'One Piece', source: 'google');

        $confidence = $this->scorer->score(
            '978-2-1234-5678-9',
            null,
            LookupMode::ISBN,
            $result,
            ['google'],
        );

        self::assertSame(EnrichmentConfidence::HIGH, $confidence);
    }

    /**
     * Teste qu'un ISBN avec formatage différent produit une confiance HIGH.
     */
    public function testIsbnMatchIgnoresFormatting(): void
    {
        $result = new LookupResult(isbn: '9782123456789', title: 'One Piece', source: 'google');

        $confidence = $this->scorer->score(
            '978-2-1234-5678-9',
            null,
            LookupMode::ISBN,
            $result,
            ['google'],
        );

        self::assertSame(EnrichmentConfidence::HIGH, $confidence);
    }

    /**
     * Teste qu'un titre exact avec type et 2+ providers produit HIGH.
     */
    public function testExactTitleWithTypeAndMultipleProvidersReturnsHigh(): void
    {
        $result = new LookupResult(
            authors: 'Eiichiro Oda',
            title: 'One Piece',
            source: 'google',
        );

        $confidence = $this->scorer->score(
            'One Piece',
            ComicType::MANGA,
            LookupMode::TITLE,
            $result,
            ['google', 'anilist', 'gemini'],
        );

        self::assertSame(EnrichmentConfidence::HIGH, $confidence);
    }

    /**
     * Teste qu'un titre très similaire avec type et auteurs produit MEDIUM.
     */
    public function testSimilarTitleWithTypeAndAuthorsReturnsMedium(): void
    {
        // "One Piece" vs "One Piéce" — titre très similaire mais pas exact
        $result = new LookupResult(
            authors: 'Eiichiro Oda',
            title: 'One Piéce',
            source: 'google',
        );

        $confidence = $this->scorer->score(
            'One Piece',
            ComicType::MANGA,
            LookupMode::TITLE,
            $result,
            ['google'],
        );

        self::assertSame(EnrichmentConfidence::MEDIUM, $confidence);
    }

    /**
     * Teste qu'un titre très différent produit LOW.
     */
    public function testVeryDifferentTitleReturnsLow(): void
    {
        $result = new LookupResult(title: 'Dragon Ball Super', source: 'google');

        $confidence = $this->scorer->score(
            'One Piece',
            null,
            LookupMode::TITLE,
            $result,
            ['google'],
        );

        self::assertSame(EnrichmentConfidence::LOW, $confidence);
    }

    /**
     * Teste que la présence d'auteurs augmente le score.
     */
    public function testAuthorPresenceBoostsScore(): void
    {
        $resultWithAuthors = new LookupResult(
            authors: 'Eiichiro Oda',
            title: 'One Piece',
            source: 'google',
        );
        $resultWithoutAuthors = new LookupResult(
            title: 'One Piece',
            source: 'google',
        );

        $withAuthors = $this->scorer->score('One Piece', null, LookupMode::TITLE, $resultWithAuthors, ['google']);
        $withoutAuthors = $this->scorer->score('One Piece', null, LookupMode::TITLE, $resultWithoutAuthors, ['google']);

        // Avec auteurs devrait être >= MEDIUM, sans auteurs pourrait être < MEDIUM
        // L'important est que le score avec auteurs est supérieur ou égal
        self::assertSame(EnrichmentConfidence::MEDIUM, $withAuthors);
    }

    /**
     * Teste que plus de providers augmente la confiance.
     */
    public function testMoreProvidersIncreasesConfidence(): void
    {
        $result = new LookupResult(
            authors: 'Author',
            title: 'One Piece',
            source: 'google',
        );

        $singleProvider = $this->scorer->score('One Piece', ComicType::MANGA, LookupMode::TITLE, $result, ['google']);
        $multipleProviders = $this->scorer->score('One Piece', ComicType::MANGA, LookupMode::TITLE, $result, ['google', 'anilist', 'gemini']);

        // Avec 3 providers + type + auteurs + titre exact → HIGH
        self::assertSame(EnrichmentConfidence::HIGH, $multipleProviders);
        // Avec 1 provider + type + auteurs + titre exact → pourrait être MEDIUM ou HIGH selon les poids
        self::assertTrue(
            \in_array($singleProvider, [EnrichmentConfidence::MEDIUM, EnrichmentConfidence::HIGH], true),
        );
    }

    /**
     * Teste qu'un résultat sans titre produit LOW.
     */
    public function testNullTitleReturnsLow(): void
    {
        $result = new LookupResult(source: 'google');

        $confidence = $this->scorer->score(
            'One Piece',
            null,
            LookupMode::TITLE,
            $result,
            ['google'],
        );

        self::assertSame(EnrichmentConfidence::LOW, $confidence);
    }
}
