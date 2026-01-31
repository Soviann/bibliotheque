# Services

Ce guide documente les services métier de l'application.

---

## IsbnLookupService

Service de recherche d'informations bibliographiques via APIs externes.

**Fichier** : `src/Service/IsbnLookupService.php`

### Responsabilités

- Recherche par ISBN (Google Books, Open Library)
- Recherche par titre (Google Books, AniList)
- Fusion des résultats de plusieurs APIs
- Détection automatique du type et des one-shots

### Dépendances

```php
public function __construct(
    private readonly HttpClientInterface $httpClient,
)
```

---

### Méthode `lookup()`

Recherche des informations par ISBN.

```php
public function lookup(string $isbn, ?string $type = null): ?array
```

**Paramètres** :
| Paramètre | Type | Description |
|-----------|------|-------------|
| `isbn` | string | ISBN-10 ou ISBN-13 (avec ou sans tirets) |
| `type` | string\|null | Type de publication (`manga`, `bd`, `comics`, `livre`) |

**Retour** :
```php
[
    'title' => 'One Piece',
    'authors' => ['Eiichiro Oda'],
    'description' => 'Luffy rêve de devenir le roi des pirates...',
    'publishedDate' => '1997-12-24',
    'publisher' => 'Glénat',
    'isbn' => '9782723456789',
    'thumbnail' => 'https://...',
    'isOneShot' => false,
    'type' => 'manga',
    'sources' => ['Google Books', 'AniList'],
]
```

**Algorithme** :
1. Normalise l'ISBN (supprime tirets et espaces)
2. Interroge Google Books
3. Interroge Open Library pour enrichir
4. Si `type === 'manga'`, interroge AniList par titre
5. Fusionne les résultats (priorité Google Books)
6. Déduit le type si non fourni

---

### Méthode `lookupByTitle()`

Recherche des informations par titre.

```php
public function lookupByTitle(string $title, ?string $type = null): ?array
```

**Paramètres** :
| Paramètre | Type | Description |
|-----------|------|-------------|
| `title` | string | Titre de la série |
| `type` | string\|null | Type de publication |

**Retour** : même format que `lookup()`

**Algorithme** :
1. Si `type === 'manga'`, interroge AniList d'abord
2. Interroge Google Books
3. Fusionne les résultats (priorité AniList pour les mangas)

---

### APIs utilisées

#### Google Books

**Endpoint** : `https://www.googleapis.com/books/v1/volumes`

**Recherche par ISBN** :
```
GET ?q=isbn:{isbn}
```

**Recherche par titre** :
```
GET ?q=intitle:{title}
```

**Données extraites** :
- `volumeInfo.title`
- `volumeInfo.authors[]`
- `volumeInfo.publisher`
- `volumeInfo.publishedDate`
- `volumeInfo.description`
- `volumeInfo.imageLinks.thumbnail`
- `volumeInfo.industryIdentifiers[]` (ISBN)
- `volumeInfo.seriesInfo` (détection one-shot)

#### Open Library

**Endpoint** : `https://openlibrary.org/isbn/{isbn}.json`

**Données extraites** :
- `authors[].name` (via requête supplémentaire)
- `publishers[]`

Utilisé pour enrichir les résultats Google Books si des champs sont manquants.

#### AniList (GraphQL)

**Endpoint** : `https://graphql.anilist.co`

**Query** :
```graphql
query ($search: String) {
  Media(search: $search, type: MANGA) {
    title { romaji english native }
    description
    coverImage { large }
    format
    volumes
    status
  }
}
```

**Données extraites** :
- `title.romaji` ou `title.english`
- `description`
- `coverImage.large` (haute résolution)
- `format` (détection ONE_SHOT)
- `volumes` + `status` (détection one-shot alternatif)

---

### Nettoyage des titres

Avant recherche AniList, le titre est nettoyé :

```php
private function cleanTitleForSearch(string $title): string
{
    // Supprime "Tome X", "Vol. X", "Volume X", "T.X", "#X"
    $patterns = [
        '/\s*-?\s*[Tt]ome\s*\d+.*$/u',
        '/\s*-?\s*[Vv]ol\.?\s*\d+.*$/u',
        '/\s*-?\s*[Vv]olume\s*\d+.*$/u',
        '/\s*-?\s*[Tt]\.\s*\d+.*$/u',
        '/\s*#\d+.*$/u',
    ];

    return trim(preg_replace($patterns, '', $title));
}
```

---

### Déduction du type

Si le type n'est pas fourni, il est déduit :

1. **Via AniList** : si trouvé, type = `manga`
2. **Via l'éditeur** : liste d'éditeurs connus

```php
private const MANGA_PUBLISHERS = [
    'Kana', 'Pika', 'Glénat Manga', 'Ki-oon', 'Kurokawa',
    'Delcourt/Tonkam', 'Kazé', 'Soleil Manga'
];

private const BD_PUBLISHERS = [
    'Dargaud', 'Dupuis', 'Le Lombard', 'Casterman',
    'Glénat', 'Delcourt', 'Soleil'
];

private const COMICS_PUBLISHERS = [
    'Urban Comics', 'Panini Comics', 'DC Comics', 'Marvel'
];
```

---

### Détection des one-shots

#### Via Google Books

```php
// Si seriesInfo est absent, c'est un volume unique
if (!isset($volumeInfo['seriesInfo'])) {
    $isOneShot = true;
}
```

#### Via AniList

```php
// Condition 1 : format ONE_SHOT
if ($media['format'] === 'ONE_SHOT') {
    $isOneShot = true;
}

// Condition 2 : 1 volume et série terminée
if ($media['volumes'] === 1 && $media['status'] === 'FINISHED') {
    $isOneShot = true;
}
```

---

### Gestion des erreurs

Le service gère gracieusement les erreurs API :

```php
try {
    $response = $this->httpClient->request('GET', $url);
    $data = $response->toArray();
} catch (TransportExceptionInterface|HttpExceptionInterface $e) {
    // Log l'erreur et continue avec les autres APIs
    return null;
}
```

---

### Exemple d'utilisation

```php
// Dans un contrôleur
public function isbnLookup(
    Request $request,
    IsbnLookupService $isbnLookup
): JsonResponse {
    $isbn = $request->query->get('isbn');
    $type = $request->query->get('type');

    $result = $isbnLookup->lookup($isbn, $type);

    if ($result === null) {
        return new JsonResponse(['error' => 'Not found'], 404);
    }

    return new JsonResponse($result);
}
```

---

### Tests

Les tests du service utilisent des mocks HTTP :

```php
// tests/Service/IsbnLookupServiceTest.php

public function testLookupReturnsGoogleBooksData(): void
{
    $mockResponse = new MockResponse(json_encode([
        'items' => [[
            'volumeInfo' => [
                'title' => 'Test Book',
                'authors' => ['Test Author'],
            ]
        ]]
    ]));

    $httpClient = new MockHttpClient($mockResponse);
    $service = new IsbnLookupService($httpClient);

    $result = $service->lookup('9781234567890');

    $this->assertEquals('Test Book', $result['title']);
}
```

---

## Étapes suivantes

- [Entités Doctrine](entites.md) - Modèle de données
- [API REST](../api/README.md) - Endpoints disponibles
