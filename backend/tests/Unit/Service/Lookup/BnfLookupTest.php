<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup;

use App\Enum\ComicType;
use App\Service\Lookup\BnfLookup;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tests unitaires pour BnfLookup.
 */
final class BnfLookupTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    private LoggerInterface&MockObject $logger;
    private BnfLookup $provider;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->provider = new BnfLookup(
            $this->httpClient,
            $this->logger,
        );
    }

    /**
     * Teste que getFieldPriority retourne 0 pour les champs non supportes.
     */
    public function testGetFieldPriorityReturnsZeroForUnsupportedFields(): void
    {
        self::assertSame(0, $this->provider->getFieldPriority('description'));
        self::assertSame(0, $this->provider->getFieldPriority('isOneShot'));
        self::assertSame(0, $this->provider->getFieldPriority('thumbnail'));
    }

    /**
     * Teste que getFieldPriority retourne 90 pour les autres champs.
     */
    public function testGetFieldPriorityReturns90ForSupportedFields(): void
    {
        self::assertSame(90, $this->provider->getFieldPriority('title'));
        self::assertSame(90, $this->provider->getFieldPriority('authors'));
        self::assertSame(90, $this->provider->getFieldPriority('publisher'));
        self::assertSame(90, $this->provider->getFieldPriority('publishedDate'));
        self::assertSame(90, $this->provider->getFieldPriority('isbn', ComicType::BD));
    }

    /**
     * Teste que getName retourne 'bnf'.
     */
    public function testGetNameReturnsBnf(): void
    {
        self::assertSame('bnf', $this->provider->getName());
    }

    /**
     * Teste que supports retourne true pour isbn et title.
     */
    public function testSupportsIsbnAndTitle(): void
    {
        self::assertTrue($this->provider->supports('isbn', null));
        self::assertTrue($this->provider->supports('title', null));
        self::assertTrue($this->provider->supports('isbn', ComicType::BD));
    }

    /**
     * Teste que supports retourne false pour les modes non supportes.
     */
    public function testDoesNotSupportOtherModes(): void
    {
        self::assertFalse($this->provider->supports('author', null));
    }

    /**
     * Teste que prepareLookup en mode isbn envoie la bonne requete SRU.
     */
    public function testPrepareLookupIsbnMode(): void
    {
        $response = $this->createMock(ResponseInterface::class);

        $this->httpClient->expects(self::once())
            ->method('request')
            ->with(
                'GET',
                'https://catalogue.bnf.fr/api/SRU',
                self::callback(static function (array $options): bool {
                    return 'bib.isbn adj "9782723489"' === $options['query']['query']
                        && 'searchRetrieve' === $options['query']['operation']
                        && 'dublincore' === $options['query']['recordSchema']
                        && '1.2' === $options['query']['version']
                        && 1 === $options['query']['maximumRecords'];
                }),
            )
            ->willReturn($response);

        $this->provider->prepareLookup('9782723489', null, 'isbn');
    }

    /**
     * Teste que prepareLookup en mode title envoie la bonne requete SRU.
     */
    public function testPrepareLookupTitleMode(): void
    {
        $response = $this->createMock(ResponseInterface::class);

        $this->httpClient->expects(self::once())
            ->method('request')
            ->with(
                'GET',
                'https://catalogue.bnf.fr/api/SRU',
                self::callback(static function (array $options): bool {
                    return 'bib.title all "One Piece"' === $options['query']['query'];
                }),
            )
            ->willReturn($response);

        $this->provider->prepareLookup('One Piece', ComicType::MANGA, 'title');
    }

    /**
     * Teste resolveLookup avec un XML valide contenant des donnees.
     */
    public function testResolveLookupSuccessWithValidXml(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <srw:searchRetrieveResponse xmlns:srw="http://www.loc.gov/zing/srw/">
                <srw:numberOfRecords>1</srw:numberOfRecords>
                <srw:records>
                    <srw:record>
                        <srw:recordData>
                            <oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"
                                       xmlns:dc="http://purl.org/dc/elements/1.1/">
                                <dc:title>One Piece. 1 / Eiichiro Oda</dc:title>
                                <dc:creator>Oda, Eiichiro (1975-....)</dc:creator>
                                <dc:publisher>Glenat (Grenoble)</dc:publisher>
                                <dc:date>2000</dc:date>
                                <dc:identifier>ISBN 9782723489003</dc:identifier>
                            </oai_dc:dc>
                        </srw:recordData>
                    </srw:record>
                </srw:records>
            </srw:searchRetrieveResponse>
            XML;

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn($xml);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('One Piece. 1', $result->title);
        self::assertSame('Eiichiro Oda', $result->authors);
        self::assertSame('Glenat', $result->publisher);
        self::assertSame('2000', $result->publishedDate);
        self::assertSame('9782723489003', $result->isbn);
        self::assertSame('bnf', $result->source);
    }

    /**
     * Teste que le titre est nettoye (suppression de la mention d'auteur apres " / ").
     */
    public function testResolveLookupCleansTitleAfterSlash(): void
    {
        $xml = $this->buildXml(
            title: 'Dragon Ball / Akira Toriyama',
            creator: 'Toriyama, Akira',
        );

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn($xml);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('Dragon Ball', $result->title);
    }

    /**
     * Teste que le nom d'auteur est inverse de "Nom, Prenom (dates)" a "Prenom Nom".
     */
    public function testResolveLookupReversesAuthorName(): void
    {
        $xml = $this->buildXml(
            creator: 'Toriyama, Akira (1955-2024)',
            title: 'Dragon Ball',
        );

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn($xml);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('Akira Toriyama', $result->authors);
    }

    /**
     * Teste resolveLookup avec zero resultats dans le XML.
     */
    public function testResolveLookupNoResults(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <srw:searchRetrieveResponse xmlns:srw="http://www.loc.gov/zing/srw/">
                <srw:numberOfRecords>0</srw:numberOfRecords>
            </srw:searchRetrieveResponse>
            XML;

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn($xml);

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('not_found', $apiMessage['status']);
    }

    /**
     * Teste resolveLookup avec un XML invalide.
     */
    public function testResolveLookupInvalidXml(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn('<invalid xml <<<');

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('error', $apiMessage['status']);
    }

    /**
     * Teste resolveLookup en cas d'erreur de transport.
     */
    public function testResolveLookupTransportException(): void
    {
        $exception = new class ('Network error') extends \RuntimeException implements TransportExceptionInterface {};

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willThrowException($exception);

        $this->logger->expects(self::once())->method('error');

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('error', $apiMessage['status']);
        self::assertSame('Erreur de connexion', $apiMessage['message']);
    }

    /**
     * Teste resolveLookup en cas d'erreur HTTP generique.
     */
    public function testResolveLookupGenericHttpError(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willThrowException(new \RuntimeException('500 Server Error'));

        $this->logger->expects(self::once())->method('warning');

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('error', $apiMessage['status']);
    }

    /**
     * Teste que l'editeur est nettoye (suppression de la ville entre parentheses).
     */
    public function testResolveLookupCleansPublisher(): void
    {
        $xml = $this->buildXml(
            publisher: 'Kana (Bruxelles)',
            title: 'Naruto',
        );

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn($xml);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('Kana', $result->publisher);
    }

    /**
     * Construit un XML SRU Dublin Core minimal pour les tests.
     */
    private function buildXml(
        string $title = 'Test',
        ?string $creator = null,
        ?string $publisher = null,
        ?string $date = null,
        ?string $identifier = null,
    ): string {
        $creatorXml = null !== $creator
            ? \sprintf('<dc:creator>%s</dc:creator>', \htmlspecialchars($creator))
            : '';
        $publisherXml = null !== $publisher
            ? \sprintf('<dc:publisher>%s</dc:publisher>', \htmlspecialchars($publisher))
            : '';
        $dateXml = null !== $date
            ? \sprintf('<dc:date>%s</dc:date>', \htmlspecialchars($date))
            : '';
        $identifierXml = null !== $identifier
            ? \sprintf('<dc:identifier>%s</dc:identifier>', \htmlspecialchars($identifier))
            : '';

        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <srw:searchRetrieveResponse xmlns:srw="http://www.loc.gov/zing/srw/">
                <srw:numberOfRecords>1</srw:numberOfRecords>
                <srw:records>
                    <srw:record>
                        <srw:recordData>
                            <oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"
                                       xmlns:dc="http://purl.org/dc/elements/1.1/">
                                <dc:title>{$title}</dc:title>
                                {$creatorXml}
                                {$publisherXml}
                                {$dateXml}
                                {$identifierXml}
                            </oai_dc:dc>
                        </srw:recordData>
                    </srw:record>
                </srw:records>
            </srw:searchRetrieveResponse>
            XML;
    }
}
