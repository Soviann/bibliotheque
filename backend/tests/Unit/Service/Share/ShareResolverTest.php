<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Share;

use App\DTO\Share\ShareUrlInfo;
use App\Entity\ComicSeries;
use App\Enum\ComicType;
use App\Message\EnrichSeriesMessage;
use App\Repository\ComicSeriesRepository;
use App\Service\Lookup\Contract\LookupResult;
use App\Service\Lookup\LookupOrchestrator;
use App\Service\Share\ShareResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class ShareResolverTest extends TestCase
{
    private MockObject&LookupOrchestrator $lookupOrchestrator;
    private MockObject&ComicSeriesRepository $repository;
    private MockObject&MessageBusInterface $messageBus;
    private ShareResolver $resolver;

    protected function setUp(): void
    {
        $this->lookupOrchestrator = $this->createMock(LookupOrchestrator::class);
        $this->repository = $this->createMock(ComicSeriesRepository::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->resolver = new ShareResolver(
            comicSeriesRepository: $this->repository,
            lookupOrchestrator: $this->lookupOrchestrator,
            messageBus: $this->messageBus,
        );
    }

    public function testResolveWithIsbnMatchReturnsMatchedAndDispatchesMessage(): void
    {
        $isbn = '2723492532';
        $info = new ShareUrlInfo(
            source: ShareUrlInfo::SOURCE_AMAZON,
            originalUrl: 'https://www.amazon.fr/dp/2723492532',
            isbn: $isbn,
        );

        $result = new LookupResult(isbn: $isbn, title: 'Astérix');
        $series = $this->createSeriesStub(42);

        $this->lookupOrchestrator
            ->expects($this->once())
            ->method('lookup')
            ->with($isbn, null)
            ->willReturn($result);

        $this->repository
            ->expects($this->once())
            ->method('findOneByTomeIsbn')
            ->with($isbn)
            ->willReturn($series);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (EnrichSeriesMessage $msg): bool {
                return 42 === $msg->seriesId && 'event:share' === $msg->triggeredBy;
            }))
            ->willReturn(new Envelope(new EnrichSeriesMessage(42, 'event:share')));

        $resolution = $this->resolver->resolve($info);

        $this->assertTrue($resolution->matched);
        $this->assertSame(42, $resolution->seriesId);
        $this->assertSame($result, $resolution->lookupResult);
    }

    public function testResolveWithTitleHintAndNoDbMatchReturnsUnmatched(): void
    {
        $info = new ShareUrlInfo(
            source: ShareUrlInfo::SOURCE_WIKIPEDIA,
            originalUrl: 'https://fr.wikipedia.org/wiki/Lanfeust_de_Troy',
            titleHint: 'Lanfeust de Troy',
        );

        $result = new LookupResult(title: 'Lanfeust de Troy', isbn: null);

        $this->lookupOrchestrator
            ->expects($this->once())
            ->method('lookupByTitle')
            ->with('Lanfeust de Troy', null)
            ->willReturn($result);

        $this->repository
            ->expects($this->once())
            ->method('findOneByFuzzyTitle')
            ->willReturn(null);

        $this->repository
            ->expects($this->once())
            ->method('findOneByFuzzyTitleAnyType')
            ->willReturn(null);

        $this->messageBus->expects($this->never())->method('dispatch');

        $resolution = $this->resolver->resolve($info);

        $this->assertFalse($resolution->matched);
        $this->assertNull($resolution->seriesId);
        $this->assertSame($result, $resolution->lookupResult);
    }

    public function testResolveWithNoIsbnNoTitleNoFallbackReturnsEmpty(): void
    {
        $info = new ShareUrlInfo(
            source: ShareUrlInfo::SOURCE_UNKNOWN,
            originalUrl: 'https://example.com/foo',
        );

        $this->lookupOrchestrator->expects($this->never())->method('lookup');
        $this->lookupOrchestrator->expects($this->never())->method('lookupByTitle');
        $this->repository->expects($this->never())->method('findOneByTomeIsbn');
        $this->repository->expects($this->never())->method('findOneByFuzzyTitle');
        $this->repository->expects($this->never())->method('findOneByFuzzyTitleAnyType');
        $this->messageBus->expects($this->never())->method('dispatch');

        $resolution = $this->resolver->resolve($info);

        $this->assertFalse($resolution->matched);
        $this->assertNull($resolution->seriesId);
        $this->assertNull($resolution->lookupResult);
    }

    public function testResolveWhenLookupReturnsNullReturnsEmpty(): void
    {
        $info = new ShareUrlInfo(
            source: ShareUrlInfo::SOURCE_AMAZON,
            originalUrl: 'https://www.amazon.fr/dp/2723492532',
            isbn: '2723492532',
        );

        $this->lookupOrchestrator
            ->expects($this->once())
            ->method('lookup')
            ->willReturn(null);

        $this->repository->expects($this->never())->method('findOneByTomeIsbn');
        $this->repository->expects($this->never())->method('findOneByFuzzyTitle');
        $this->repository->expects($this->never())->method('findOneByFuzzyTitleAnyType');
        $this->messageBus->expects($this->never())->method('dispatch');

        $resolution = $this->resolver->resolve($info);

        $this->assertFalse($resolution->matched);
        $this->assertNull($resolution->lookupResult);
    }

    public function testResolveIsbnLookupFailsButFuzzyTitleMatchesReturnsMatched(): void
    {
        $info = new ShareUrlInfo(
            source: ShareUrlInfo::SOURCE_BEDETHEQUE,
            originalUrl: 'https://www.bedetheque.com/serie-12345-BD-Asterix.html',
            titleHint: 'BD Asterix',
            type: ComicType::BD,
        );

        $result = new LookupResult(title: 'Astérix', isbn: null);
        $series = $this->createSeriesStub(7);

        $this->lookupOrchestrator
            ->expects($this->once())
            ->method('lookupByTitle')
            ->with('BD Asterix', ComicType::BD)
            ->willReturn($result);

        // isbn est null donc pas d'appel à findOneByTomeIsbn
        $this->repository
            ->expects($this->never())
            ->method('findOneByTomeIsbn');

        $this->repository
            ->expects($this->once())
            ->method('findOneByFuzzyTitle')
            ->with('Astérix', ComicType::BD)
            ->willReturn($series);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new EnrichSeriesMessage(7, 'event:share')));

        $resolution = $this->resolver->resolve($info);

        $this->assertTrue($resolution->matched);
        $this->assertSame(7, $resolution->seriesId);
    }

    public function testResolveIsbnMatchFailsThenFuzzyTitleMatches(): void
    {
        $isbn = '9782344000000';
        $info = new ShareUrlInfo(
            source: ShareUrlInfo::SOURCE_AMAZON,
            originalUrl: 'https://www.amazon.fr/dp/9782344000000',
            isbn: $isbn,
        );

        $result = new LookupResult(isbn: $isbn, title: 'Thorgal');
        $series = $this->createSeriesStub(15);

        $this->lookupOrchestrator
            ->expects($this->once())
            ->method('lookup')
            ->with($isbn, null)
            ->willReturn($result);

        $this->repository
            ->expects($this->once())
            ->method('findOneByTomeIsbn')
            ->with($isbn)
            ->willReturn(null); // ISBN ne matche pas

        $this->repository
            ->expects($this->once())
            ->method('findOneByFuzzyTitle')
            ->with('Thorgal', ComicType::BD)
            ->willReturn($series); // fuzzy title matche

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (EnrichSeriesMessage $msg): bool {
                return 15 === $msg->seriesId && 'event:share' === $msg->triggeredBy;
            }))
            ->willReturn(new Envelope(new EnrichSeriesMessage(15, 'event:share')));

        $resolution = $this->resolver->resolve($info);

        $this->assertTrue($resolution->matched);
        $this->assertSame(15, $resolution->seriesId);
    }

    public function testResolveUsesFallbackTitleWhenNoIsbnOrHint(): void
    {
        $info = new ShareUrlInfo(
            source: ShareUrlInfo::SOURCE_UNKNOWN,
            originalUrl: 'https://example.com/foo',
        );

        $result = new LookupResult(title: 'Mon titre');

        $this->lookupOrchestrator
            ->expects($this->once())
            ->method('lookupByTitle')
            ->with('Mon titre fallback')
            ->willReturn($result);

        $this->repository
            ->expects($this->once())
            ->method('findOneByFuzzyTitle')
            ->willReturn(null);

        $this->repository
            ->expects($this->once())
            ->method('findOneByFuzzyTitleAnyType')
            ->willReturn(null);

        $this->messageBus->expects($this->never())->method('dispatch');

        $resolution = $this->resolver->resolve($info, 'Mon titre fallback');

        $this->assertFalse($resolution->matched);
        $this->assertSame($result, $resolution->lookupResult);
    }

    /**
     * Crée un stub de ComicSeries avec un ID défini.
     */
    private function createSeriesStub(int $id): ComicSeries
    {
        $series = $this->createStub(ComicSeries::class);
        $series->method('getId')->willReturn($id);

        return $series;
    }
}
