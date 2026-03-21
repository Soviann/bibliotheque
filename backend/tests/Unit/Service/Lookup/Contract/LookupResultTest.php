<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup\Contract;

use App\Service\Lookup\Contract\LookupResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour LookupResult.
 */
final class LookupResultTest extends TestCase
{
    /**
     * Teste le constructeur avec tous les champs renseignes.
     */
    public function testConstructorWithAllFields(): void
    {
        $result = new LookupResult(
            amazonUrl: 'https://www.amazon.fr/dp/B08N5WRWNW',
            authors: 'Eiichiro Oda',
            description: 'Un manga de pirates',
            isbn: '978-2723489',
            isOneShot: false,
            latestPublishedIssue: 107,
            publishedDate: '1997-07-22',
            publisher: 'Glenat',
            source: 'google_books',
            thumbnail: 'https://example.com/cover.jpg',
            title: 'One Piece',
            tomeEnd: 6,
            tomeNumber: 4,
        );

        self::assertSame('https://www.amazon.fr/dp/B08N5WRWNW', $result->amazonUrl);
        self::assertSame('Eiichiro Oda', $result->authors);
        self::assertSame('Un manga de pirates', $result->description);
        self::assertSame('978-2723489', $result->isbn);
        self::assertFalse($result->isOneShot);
        self::assertSame(107, $result->latestPublishedIssue);
        self::assertSame('1997-07-22', $result->publishedDate);
        self::assertSame('Glenat', $result->publisher);
        self::assertSame('google_books', $result->source);
        self::assertSame('https://example.com/cover.jpg', $result->thumbnail);
        self::assertSame('One Piece', $result->title);
        self::assertSame(6, $result->tomeEnd);
        self::assertSame(4, $result->tomeNumber);
    }

    /**
     * Teste le constructeur avec les valeurs par defaut.
     */
    public function testConstructorWithDefaults(): void
    {
        $result = new LookupResult();

        self::assertNull($result->amazonUrl);
        self::assertNull($result->authors);
        self::assertNull($result->description);
        self::assertNull($result->isbn);
        self::assertNull($result->isOneShot);
        self::assertNull($result->latestPublishedIssue);
        self::assertNull($result->publishedDate);
        self::assertNull($result->publisher);
        self::assertSame('', $result->source);
        self::assertNull($result->thumbnail);
        self::assertNull($result->title);
        self::assertNull($result->tomeEnd);
        self::assertNull($result->tomeNumber);
    }

    /**
     * Teste que jsonSerialize retourne tous les champs sauf source.
     */
    public function testJsonSerializeExcludesSource(): void
    {
        $result = new LookupResult(
            amazonUrl: 'https://www.amazon.fr/dp/B08N5WRWNW',
            authors: 'Toriyama',
            description: 'Dragon Ball',
            isbn: '1234567890',
            isOneShot: true,
            latestPublishedIssue: 42,
            publishedDate: '1984',
            publisher: 'Glenat',
            source: 'anilist',
            thumbnail: 'https://example.com/db.jpg',
            title: 'Dragon Ball',
            tomeEnd: 6,
            tomeNumber: 4,
        );

        $json = $result->jsonSerialize();

        self::assertArrayNotHasKey('source', $json);
        self::assertSame('https://www.amazon.fr/dp/B08N5WRWNW', $json['amazonUrl']);
        self::assertSame('Toriyama', $json['authors']);
        self::assertSame('Dragon Ball', $json['description']);
        self::assertSame('1234567890', $json['isbn']);
        self::assertTrue($json['isOneShot']);
        self::assertSame(42, $json['latestPublishedIssue']);
        self::assertSame('1984', $json['publishedDate']);
        self::assertSame('Glenat', $json['publisher']);
        self::assertSame('https://example.com/db.jpg', $json['thumbnail']);
        self::assertSame('Dragon Ball', $json['title']);
        self::assertSame(6, $json['tomeEnd']);
        self::assertSame(4, $json['tomeNumber']);
    }

    /**
     * Teste isComplete retourne true quand tous les champs principaux sont non-null.
     */
    public function testIsCompleteReturnsTrueWhenAllMainFieldsSet(): void
    {
        $result = new LookupResult(
            authors: 'Oda',
            description: 'Synopsis',
            publishedDate: '1997',
            publisher: 'Glenat',
            thumbnail: 'https://example.com/img.jpg',
            title: 'One Piece',
        );

        self::assertTrue($result->isComplete());
    }

    /**
     * Teste isComplete retourne false quand un champ principal est null.
     */
    #[DataProvider('incompleteFieldsProvider')]
    public function testIsCompleteReturnsFalseWhenFieldIsNull(
        ?string $authors,
        ?string $description,
        ?string $publishedDate,
        ?string $publisher,
        ?string $thumbnail,
        ?string $title,
    ): void {
        $result = new LookupResult(
            authors: $authors,
            description: $description,
            publishedDate: $publishedDate,
            publisher: $publisher,
            thumbnail: $thumbnail,
            title: $title,
        );

        self::assertFalse($result->isComplete());
    }

    /**
     * @return iterable<string, array{?string, ?string, ?string, ?string, ?string, ?string}>
     */
    public static function incompleteFieldsProvider(): iterable
    {
        yield 'authors manquant' => [null, 'desc', '2000', 'pub', 'thumb', 'title'];
        yield 'description manquante' => ['auth', null, '2000', 'pub', 'thumb', 'title'];
        yield 'publishedDate manquante' => ['auth', 'desc', null, 'pub', 'thumb', 'title'];
        yield 'publisher manquant' => ['auth', 'desc', '2000', null, 'thumb', 'title'];
        yield 'thumbnail manquant' => ['auth', 'desc', '2000', 'pub', null, 'title'];
        yield 'title manquant' => ['auth', 'desc', '2000', 'pub', 'thumb', null];
    }

    /**
     * Teste withIsbn retourne une nouvelle instance avec l'ISBN defini.
     */
    public function testWithIsbnReturnsNewInstance(): void
    {
        $original = new LookupResult(
            amazonUrl: 'https://www.amazon.fr/dp/B08N5WRWNW',
            authors: 'Oda',
            source: 'google_books',
            title: 'One Piece',
            tomeEnd: 6,
            tomeNumber: 4,
        );

        $withIsbn = $original->withIsbn('978-2723489');

        self::assertNotSame($original, $withIsbn);
        self::assertNull($original->isbn);
        self::assertSame('978-2723489', $withIsbn->isbn);
        self::assertSame('https://www.amazon.fr/dp/B08N5WRWNW', $withIsbn->amazonUrl);
        self::assertSame('Oda', $withIsbn->authors);
        self::assertSame('google_books', $withIsbn->source);
        self::assertSame('One Piece', $withIsbn->title);
        self::assertSame(6, $withIsbn->tomeEnd);
        self::assertSame(4, $withIsbn->tomeNumber);
    }

    /**
     * Teste la deserialisation avec des donnees completes.
     */
    public function testUnserializeWithFullData(): void
    {
        $original = new LookupResult(
            amazonUrl: 'https://www.amazon.fr/dp/B08N5WRWNW',
            authors: 'Oda',
            description: 'Pirates',
            isbn: '1234567890',
            isOneShot: false,
            latestPublishedIssue: 107,
            publishedDate: '1997',
            publisher: 'Glenat',
            source: 'test',
            thumbnail: 'https://example.com/img.jpg',
            title: 'One Piece',
            tomeEnd: 6,
            tomeNumber: 4,
        );

        /** @var LookupResult $result */
        $result = \unserialize(\serialize($original));

        self::assertSame('https://www.amazon.fr/dp/B08N5WRWNW', $result->amazonUrl);
        self::assertSame('Oda', $result->authors);
        self::assertSame('Pirates', $result->description);
        self::assertSame('1234567890', $result->isbn);
        self::assertFalse($result->isOneShot);
        self::assertSame(107, $result->latestPublishedIssue);
        self::assertSame('1997', $result->publishedDate);
        self::assertSame('Glenat', $result->publisher);
        self::assertSame('test', $result->source);
        self::assertSame('https://example.com/img.jpg', $result->thumbnail);
        self::assertSame('One Piece', $result->title);
        self::assertSame(6, $result->tomeEnd);
        self::assertSame(4, $result->tomeNumber);
    }

    /**
     * Teste la deserialisation avec des cles manquantes (defaut gracieux).
     *
     * Simule un objet serialise avant l'ajout de certaines proprietes.
     * Le format serialise ne contient que 'title' et 'source', les autres champs
     * sont absents et doivent recevoir leur valeur par defaut.
     */
    public function testUnserializeWithMissingKeys(): void
    {
        $serialized = 'O:31:"App\\Service\\Lookup\\LookupResult":2:{s:5:"title";s:4:"Test";s:6:"source";s:4:"test";}';

        /** @var LookupResult $result */
        $result = \unserialize($serialized);

        self::assertSame('Test', $result->title);
        self::assertSame('test', $result->source);
        self::assertNull($result->authors);
        self::assertNull($result->description);
        self::assertNull($result->isbn);
        self::assertNull($result->isOneShot);
        self::assertNull($result->latestPublishedIssue);
        self::assertNull($result->publishedDate);
        self::assertNull($result->publisher);
        self::assertNull($result->thumbnail);
    }

    /**
     * Teste la deserialisation avec des types incorrects (cast vers null/defaut).
     */
    public function testUnserializeWithWrongTypes(): void
    {
        $serialized = 'O:31:"App\\Service\\Lookup\\LookupResult":10:{s:7:"authors";i:123;s:11:"description";b:1;s:4:"isbn";i:456;s:9:"isOneShot";s:10:"not_a_bool";s:20:"latestPublishedIssue";s:10:"not_an_int";s:13:"publishedDate";i:42;s:9:"publisher";N;s:6:"source";i:999;s:9:"thumbnail";b:0;s:5:"title";i:0;}';

        /** @var LookupResult $result */
        $result = \unserialize($serialized);

        self::assertNull($result->authors);
        self::assertNull($result->description);
        self::assertNull($result->isbn);
        self::assertNull($result->isOneShot);
        self::assertNull($result->latestPublishedIssue);
        self::assertNull($result->publishedDate);
        self::assertNull($result->publisher);
        // source 999 (int) is not a string, so __unserialize defaults to ''
        self::assertSame('', $result->source);
        self::assertNull($result->thumbnail);
        self::assertNull($result->title);
    }
}
