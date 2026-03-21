<?php

declare(strict_types=1);

namespace App\Service\Lookup;

use App\Enum\ApiLookupStatus;
use App\Enum\ComicType;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Provider de recherche via l'API SRU du catalogue général de la BnF.
 *
 * Recherche par ISBN via bib.isbn, par titre via bib.title.
 * Réponses au format Dublin Core (XML).
 */
#[AutoconfigureTag('app.lookup_provider', ['priority' => 90])]
final class BnfLookup extends AbstractLookupProvider
{
    private const string API_URL = 'https://catalogue.bnf.fr/api/SRU';

    /** @var list<string> Champs non disponibles depuis la BnF */
    private const array UNSUPPORTED_FIELDS = ['description', 'isOneShot', 'thumbnail'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getFieldPriority(string $field, ?ComicType $type = null): int
    {
        if (\in_array($field, self::UNSUPPORTED_FIELDS, true)) {
            return 0;
        }

        return 90;
    }

    public function getName(): string
    {
        return 'bnf';
    }

    public function prepareLookup(string $query, ?ComicType $type, string $mode = 'title'): mixed
    {
        $this->resetApiMessage();

        $sruQuery = 'isbn' === $mode
            ? \sprintf('bib.isbn adj "%s"', $query)
            : \sprintf('bib.title all "%s"', $query);

        return $this->httpClient->request('GET', self::API_URL, [
            'query' => [
                'maximumRecords' => 1,
                'operation' => 'searchRetrieve',
                'query' => $sruQuery,
                'recordSchema' => 'dublincore',
                'version' => '1.2',
            ],
            'timeout' => 10,
        ]);
    }

    public function resolveLookup(mixed $state): ?LookupResult
    {
        \assert($state instanceof ResponseInterface);
        try {
            $content = $state->getContent();
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Erreur réseau BnF : {error}', ['error' => $e->getMessage()]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Erreur de connexion');

            return null;
        } catch (\Throwable $e) {
            $this->logger->warning('Erreur HTTP BnF : {error}', ['error' => $e->getMessage()]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, \sprintf('Erreur HTTP : %s', $e->getMessage()));

            return null;
        }

        return $this->parseResponse($content);
    }

    public function supports(string $mode, ?ComicType $type): bool
    {
        return \in_array($mode, ['isbn', 'title'], true);
    }

    /**
     * Nettoie le nom d'un auteur BnF : "Nom, Prénom (dates)" → "Prénom Nom".
     */
    private function cleanAuthorName(string $raw): string
    {
        // Supprimer le suffixe de rôle BnF après les dates : "(1975-....). Auteur du texte"
        // Format : parenthèses de dates suivies d'un point et du rôle
        $cleaned = \trim((string) \preg_replace('/\([^)]*\)\.\s+.*$/', '', $raw));

        // Supprimer les dates entre parenthèses restantes : "(1926-1977)" ou "(1975-....)"
        $cleaned = \trim((string) \preg_replace('/\s*\([^)]*\)/', '', $cleaned));

        // Si format "Nom, Prénom" → "Prénom Nom"
        if (\str_contains($cleaned, ',')) {
            $parts = \explode(',', $cleaned, 2);

            return \trim($parts[1]).' '.\trim($parts[0]);
        }

        return $cleaned;
    }

    /**
     * Nettoie le nom d'un éditeur : "Éditeur (Ville)" → "Éditeur".
     */
    private function cleanPublisher(string $raw): string
    {
        return \trim((string) \preg_replace('/\s*\([^)]*\)\s*$/', '', $raw));
    }

    /**
     * Nettoie le titre : supprime les mentions d'auteur après " / ".
     */
    private function cleanTitle(string $raw): string
    {
        $parts = \explode(' / ', $raw, 2);

        return \trim($parts[0]);
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Extrait l'ISBN depuis un identifiant Dublin Core : "ISBN xxx" → "xxx".
     *
     * @param list<string> $identifiers
     */
    private function extractIsbn(array $identifiers): ?string
    {
        foreach ($identifiers as $identifier) {
            if (\str_starts_with($identifier, 'ISBN ')) {
                return \trim(\substr($identifier, 5));
            }
        }

        return null;
    }

    /**
     * Parse la réponse XML SRU Dublin Core.
     */
    private function parseResponse(string $xml): ?LookupResult
    {
        $previousLibxmlState = \libxml_use_internal_errors(true);
        $doc = \simplexml_load_string($xml);

        if (false === $doc) {
            \libxml_clear_errors();
            \libxml_use_internal_errors($previousLibxmlState);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Réponse XML invalide');

            return null;
        }

        \libxml_clear_errors();
        \libxml_use_internal_errors($previousLibxmlState);

        $doc->registerXPathNamespace('srw', 'http://www.loc.gov/zing/srw/');
        $doc->registerXPathNamespace('oai_dc', 'http://www.openarchives.org/OAI/2.0/oai_dc/');
        $doc->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');

        // Vérifier le nombre de résultats
        $numberOfRecords = $doc->xpath('//srw:numberOfRecords');
        if (empty($numberOfRecords) || 0 === (int) $numberOfRecords[0]) {
            $this->recordApiMessage(ApiLookupStatus::NOT_FOUND, 'Aucun résultat');

            return null;
        }

        // Extraire le premier enregistrement
        $titles = $doc->xpath('//srw:recordData/oai_dc:dc/dc:title');
        if (empty($titles)) {
            $this->recordApiMessage(ApiLookupStatus::NOT_FOUND, 'Aucun résultat');

            return null;
        }

        $title = $this->cleanTitle((string) $titles[0]);
        if ('' === $title) {
            $this->recordApiMessage(ApiLookupStatus::NOT_FOUND, 'Aucun résultat');

            return null;
        }

        // Auteurs
        $creators = $doc->xpath('//srw:recordData/oai_dc:dc/dc:creator');
        $authorNames = [];
        if (!empty($creators)) {
            foreach ($creators as $creator) {
                $authorNames[] = $this->cleanAuthorName((string) $creator);
            }
        }
        $authors = \count($authorNames) > 0 ? \implode(', ', $authorNames) : null;

        // Éditeur
        $publishers = $doc->xpath('//srw:recordData/oai_dc:dc/dc:publisher');
        $publisher = empty($publishers) ? null : $this->cleanPublisher((string) $publishers[0]);

        // Date
        $dates = $doc->xpath('//srw:recordData/oai_dc:dc/dc:date');
        $publishedDate = empty($dates) ? null : (string) $dates[0];

        // Identifiants (ISBN)
        $identifierNodes = $doc->xpath('//srw:recordData/oai_dc:dc/dc:identifier');
        $identifiers = [];
        if (!empty($identifierNodes)) {
            foreach ($identifierNodes as $node) {
                $identifiers[] = (string) $node;
            }
        }
        $isbn = $this->extractIsbn($identifiers);

        $this->recordApiMessage(ApiLookupStatus::SUCCESS, 'Données trouvées');

        return new LookupResult(
            authors: $authors,
            isbn: $isbn,
            publishedDate: $publishedDate,
            publisher: $publisher,
            source: 'bnf',
            title: $title,
        );
    }
}
