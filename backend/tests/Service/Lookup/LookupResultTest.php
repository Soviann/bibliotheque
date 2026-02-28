<?php

declare(strict_types=1);

namespace App\Tests\Service\Lookup;

use App\Service\Lookup\LookupResult;
use PHPUnit\Framework\TestCase;

class LookupResultTest extends TestCase
{
    public function testCreateWithAllFields(): void
    {
        $result = new LookupResult(
            authors: 'John Doe',
            description: 'A great book',
            isbn: '9781234567890',
            isOneShot: false,
            latestPublishedIssue: 10,
            publishedDate: '2020-01-01',
            publisher: 'Great Publisher',
            source: 'google_books',
            thumbnail: 'https://example.com/cover.jpg',
            title: 'Test Book',
        );

        self::assertSame('John Doe', $result->authors);
        self::assertSame('A great book', $result->description);
        self::assertSame('9781234567890', $result->isbn);
        self::assertFalse($result->isOneShot);
        self::assertSame(10, $result->latestPublishedIssue);
        self::assertSame('2020-01-01', $result->publishedDate);
        self::assertSame('Great Publisher', $result->publisher);
        self::assertSame('google_books', $result->source);
        self::assertSame('https://example.com/cover.jpg', $result->thumbnail);
        self::assertSame('Test Book', $result->title);
    }

    public function testCreateWithMinimalFields(): void
    {
        $result = new LookupResult(source: 'open_library', title: 'Minimal Book');

        self::assertNull($result->authors);
        self::assertNull($result->description);
        self::assertNull($result->isbn);
        self::assertNull($result->isOneShot);
        self::assertNull($result->latestPublishedIssue);
        self::assertNull($result->publishedDate);
        self::assertNull($result->publisher);
        self::assertSame('open_library', $result->source);
        self::assertNull($result->thumbnail);
        self::assertSame('Minimal Book', $result->title);
    }

    public function testIsComplete(): void
    {
        $complete = new LookupResult(
            authors: 'Author',
            description: 'Desc',
            publishedDate: '2020',
            publisher: 'Pub',
            source: 'test',
            thumbnail: 'https://img.jpg',
            title: 'Title',
        );
        self::assertTrue($complete->isComplete());

        $incomplete = new LookupResult(source: 'test', title: 'Title');
        self::assertFalse($incomplete->isComplete());
    }

    public function testWithIsbnReturnsNewInstanceWithIsbn(): void
    {
        $result = new LookupResult(latestPublishedIssue: 5, source: 'test', title: 'Book');
        $withIsbn = $result->withIsbn('9781234567890');

        self::assertNull($result->isbn);
        self::assertSame('9781234567890', $withIsbn->isbn);
        self::assertSame('Book', $withIsbn->title);
        self::assertSame(5, $withIsbn->latestPublishedIssue);
    }

    public function testUnserializeHandlesMissingProperties(): void
    {
        // Simule un objet sérialisé avant l'ajout de latestPublishedIssue (cache périmé)
        $oldSerialized = 'O:31:"App\Service\Lookup\LookupResult":9:{s:7:"authors";s:9:"Jim Davis";s:11:"description";s:7:"Un chat";s:4:"isbn";N;s:9:"isOneShot";N;s:13:"publishedDate";N;s:9:"publisher";N;s:6:"source";s:6:"gemini";s:9:"thumbnail";N;s:5:"title";s:8:"Garfield";}';

        $result = \unserialize($oldSerialized);

        self::assertInstanceOf(LookupResult::class, $result);
        self::assertSame('Jim Davis', $result->authors);
        self::assertSame('Garfield', $result->title);
        self::assertNull($result->latestPublishedIssue);
    }

    public function testJsonSerializeReturnsAllFieldsExceptSource(): void
    {
        $result = new LookupResult(
            authors: 'John Doe',
            description: 'A great book',
            isbn: '9781234567890',
            isOneShot: false,
            latestPublishedIssue: 10,
            publishedDate: '2020-01-01',
            publisher: 'Great Publisher',
            source: 'google_books',
            thumbnail: 'https://example.com/cover.jpg',
            title: 'Test Book',
        );

        $serialized = $result->jsonSerialize();

        self::assertSame([
            'authors' => 'John Doe',
            'description' => 'A great book',
            'isbn' => '9781234567890',
            'isOneShot' => false,
            'latestPublishedIssue' => 10,
            'publishedDate' => '2020-01-01',
            'publisher' => 'Great Publisher',
            'thumbnail' => 'https://example.com/cover.jpg',
            'title' => 'Test Book',
        ], $serialized);
        self::assertArrayNotHasKey('source', $serialized);
    }
}
