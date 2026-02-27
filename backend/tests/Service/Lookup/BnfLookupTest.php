<?php

declare(strict_types=1);

namespace App\Tests\Service\Lookup;

use App\Enum\ComicType;
use App\Service\Lookup\BnfLookup;
use App\Service\Lookup\LookupResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class BnfLookupTest extends TestCase
{
    public function testGetName(): void
    {
        $provider = new BnfLookup(new MockHttpClient(), new NullLogger());

        self::assertSame('bnf', $provider->getName());
    }

    public function testGetFieldPriorityReturnsDefaultForStandardFields(): void
    {
        $provider = new BnfLookup(new MockHttpClient(), new NullLogger());

        self::assertSame(90, $provider->getFieldPriority('title'));
        self::assertSame(90, $provider->getFieldPriority('authors'));
        self::assertSame(90, $provider->getFieldPriority('publisher'));
        self::assertSame(90, $provider->getFieldPriority('isbn'));
        self::assertSame(90, $provider->getFieldPriority('publishedDate'));
    }

    public function testGetFieldPriorityReturnsZeroForUnsupportedFields(): void
    {
        $provider = new BnfLookup(new MockHttpClient(), new NullLogger());

        self::assertSame(0, $provider->getFieldPriority('description'));
        self::assertSame(0, $provider->getFieldPriority('thumbnail'));
        self::assertSame(0, $provider->getFieldPriority('isOneShot'));
    }

    public function testSupportsBothIsbnAndTitleModes(): void
    {
        $provider = new BnfLookup(new MockHttpClient(), new NullLogger());

        self::assertTrue($provider->supports('isbn', null));
        self::assertTrue($provider->supports('isbn', ComicType::BD));
        self::assertTrue($provider->supports('title', null));
        self::assertTrue($provider->supports('title', ComicType::MANGA));
        self::assertFalse($provider->supports('other', null));
    }

    public function testLookupByIsbnReturnsData(): void
    {
        $xml = $this->buildSruResponse([
            'title' => 'Astérix le Gaulois',
            'creator' => 'Goscinny, René (1926-1977)',
            'publisher' => 'Hachette (Paris)',
            'date' => '1961',
            'identifier' => 'ISBN 9782012101333',
        ]);

        $response = new MockResponse($xml, ['response_headers' => ['content-type' => 'text/xml']]);
        $provider = new BnfLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, '9782012101333', ComicType::BD, 'isbn');

        self::assertInstanceOf(LookupResult::class, $result);
        self::assertSame('Astérix le Gaulois', $result->title);
        self::assertSame('René Goscinny', $result->authors);
        self::assertSame('Hachette', $result->publisher);
        self::assertSame('1961', $result->publishedDate);
        self::assertSame('bnf', $result->source);
    }

    public function testLookupByTitleReturnsData(): void
    {
        $xml = $this->buildSruResponse([
            'title' => 'One Piece. 56',
            'creator' => 'Oda, Eiichirō (1975-....)',
            'publisher' => 'Glénat (Grenoble)',
            'date' => '2011',
            'identifier' => 'ISBN 9782723478168',
        ]);

        $response = new MockResponse($xml, ['response_headers' => ['content-type' => 'text/xml']]);
        $provider = new BnfLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, 'One Piece', ComicType::MANGA, 'title');

        self::assertInstanceOf(LookupResult::class, $result);
        self::assertSame('One Piece. 56', $result->title);
        self::assertSame('Eiichirō Oda', $result->authors);
        self::assertSame('Glénat', $result->publisher);
        self::assertSame('2011', $result->publishedDate);
    }

    public function testLookupCleansTitleWithAuthorCredits(): void
    {
        $xml = $this->buildSruResponse([
            'title' => 'La colère du papillon / texte Erik Arnoux ; dessin Frank Brichau',
            'creator' => 'Arnoux, Erik (1956-....)',
            'date' => '1999',
        ]);

        $response = new MockResponse($xml, ['response_headers' => ['content-type' => 'text/xml']]);
        $provider = new BnfLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, '9782723428071', null, 'isbn');

        self::assertSame('La colère du papillon', $result->title);
    }

    public function testLookupHandlesMultipleCreators(): void
    {
        $xml = $this->buildSruResponse([
            'title' => 'Tintin au Tibet',
            'creators' => ['Hergé (1907-1983)', 'Rodier, Yves (1967-....)'],
            'publisher' => 'Casterman (Bruxelles)',
            'date' => '1960',
        ]);

        $response = new MockResponse($xml, ['response_headers' => ['content-type' => 'text/xml']]);
        $provider = new BnfLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, '9782203001190', null, 'isbn');

        self::assertSame('Hergé, Yves Rodier', $result->authors);
        self::assertSame('Casterman', $result->publisher);
    }

    public function testLookupHandlesCreatorWithoutComma(): void
    {
        $xml = $this->buildSruResponse([
            'title' => 'Test BD',
            'creator' => 'Hergé (1907-1983)',
            'date' => '1960',
        ]);

        $response = new MockResponse($xml, ['response_headers' => ['content-type' => 'text/xml']]);
        $provider = new BnfLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, 'test', null, 'title');

        self::assertSame('Hergé', $result->authors);
    }

    public function testLookupReturnsNullWhenNoResults(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<srw:searchRetrieveResponse xmlns:srw="http://www.loc.gov/zing/srw/">
  <srw:version>1.2</srw:version>
  <srw:numberOfRecords>0</srw:numberOfRecords>
  <srw:records/>
</srw:searchRetrieveResponse>
XML;

        $response = new MockResponse($xml, ['response_headers' => ['content-type' => 'text/xml']]);
        $provider = new BnfLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, '0000000000000', null, 'isbn');

        self::assertNull($result);
        self::assertSame('not_found', $provider->getLastApiMessage()['status']);
    }

    public function testLookupHandlesNetworkErrors(): void
    {
        $response = new MockResponse('', ['error' => 'Connection failed']);

        $provider = new BnfLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, '9782012101333', null, 'isbn');

        self::assertNull($result);
        self::assertSame('error', $provider->getLastApiMessage()['status']);
    }

    public function testLookupHandlesInvalidXml(): void
    {
        $response = new MockResponse('not xml at all', ['response_headers' => ['content-type' => 'text/xml']]);

        $provider = new BnfLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, '9782012101333', null, 'isbn');

        self::assertNull($result);
        self::assertSame('error', $provider->getLastApiMessage()['status']);
    }

    public function testLookupExtractsIsbnFromIdentifier(): void
    {
        $xml = $this->buildSruResponse([
            'title' => 'Test Book',
            'identifiers' => ['ISBN 978-2-01-210133-3', 'ark:/12148/cb11937828s'],
        ]);

        $response = new MockResponse($xml, ['response_headers' => ['content-type' => 'text/xml']]);
        $provider = new BnfLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, 'test', null, 'title');

        self::assertSame('978-2-01-210133-3', $result->isbn);
    }

    public function testLookupReturnsNullWhenRecordHasNoTitle(): void
    {
        $xml = $this->buildSruResponse([
            'publisher' => 'Glénat',
            'date' => '2020',
        ]);

        $response = new MockResponse($xml, ['response_headers' => ['content-type' => 'text/xml']]);
        $provider = new BnfLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, 'test', null, 'isbn');

        self::assertNull($result);
        self::assertSame('not_found', $provider->getLastApiMessage()['status']);
    }

    private function doLookup(BnfLookup $provider, string $query, ?ComicType $type, string $mode = 'title'): ?LookupResult
    {
        $state = $provider->prepareLookup($query, $type, $mode);

        return $provider->resolveLookup($state);
    }

    /**
     * Construit une réponse SRU Dublin Core XML.
     *
     * @param array<string, string|list<string>> $fields
     */
    private function buildSruResponse(array $fields, int $numberOfRecords = 1): string
    {
        $dcFields = '';

        if (isset($fields['title'])) {
            $dcFields .= \sprintf('          <dc:title>%s</dc:title>', \htmlspecialchars((string) $fields['title']))."\n";
        }

        if (isset($fields['creators'])) {
            foreach ($fields['creators'] as $creator) {
                $dcFields .= \sprintf('          <dc:creator>%s</dc:creator>', \htmlspecialchars($creator))."\n";
            }
        } elseif (isset($fields['creator'])) {
            $dcFields .= \sprintf('          <dc:creator>%s</dc:creator>', \htmlspecialchars((string) $fields['creator']))."\n";
        }

        if (isset($fields['publisher'])) {
            $dcFields .= \sprintf('          <dc:publisher>%s</dc:publisher>', \htmlspecialchars((string) $fields['publisher']))."\n";
        }

        if (isset($fields['date'])) {
            $dcFields .= \sprintf('          <dc:date>%s</dc:date>', \htmlspecialchars((string) $fields['date']))."\n";
        }

        if (isset($fields['identifiers'])) {
            foreach ($fields['identifiers'] as $identifier) {
                $dcFields .= \sprintf('          <dc:identifier>%s</dc:identifier>', \htmlspecialchars($identifier))."\n";
            }
        } elseif (isset($fields['identifier'])) {
            $dcFields .= \sprintf('          <dc:identifier>%s</dc:identifier>', \htmlspecialchars((string) $fields['identifier']))."\n";
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<srw:searchRetrieveResponse xmlns:srw="http://www.loc.gov/zing/srw/">
  <srw:version>1.2</srw:version>
  <srw:numberOfRecords>{$numberOfRecords}</srw:numberOfRecords>
  <srw:records>
    <srw:record>
      <srw:recordSchema>info:srw/schema/1/dc-v1.1</srw:recordSchema>
      <srw:recordPacking>xml</srw:recordPacking>
      <srw:recordData>
        <oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/" xmlns:dc="http://purl.org/dc/elements/1.1/">
{$dcFields}        </oai_dc:dc>
      </srw:recordData>
    </srw:record>
  </srw:records>
</srw:searchRetrieveResponse>
XML;
    }
}
