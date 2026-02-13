# Design — Intégration Gemini + Refactoring Lookup

**Issue**: #44 — Utiliser Gemini (Google AI) pour enrichir les données des séries
**Date**: 2026-02-13

## Contexte

`IsbnLookupService` (~860 lignes) gère Google Books, Open Library et AniList dans une seule classe. L'ajout de Gemini est l'occasion de refactorer vers une architecture provider-based.

## Architecture

### Interface commune

```php
interface LookupProviderInterface {
    public function getName(): string;
    public function supports(string $mode, ?ComicType $type): bool;
    public function lookup(string $query, ?ComicType $type): ?LookupResult;
}

interface EnrichableLookupProviderInterface extends LookupProviderInterface {
    public function enrich(LookupResult $partial, ?ComicType $type): ?LookupResult;
}
```

Modes : `'isbn'`, `'title'`.

### DTO `LookupResult`

```php
class LookupResult {
    public function __construct(
        public readonly ?string $authors = null,
        public readonly ?string $description = null,
        public readonly ?string $isbn = null,
        public readonly ?bool $isOneShot = null,
        public readonly ?string $publishedDate = null,
        public readonly ?string $publisher = null,
        public readonly string $source,
        public readonly ?string $thumbnail = null,
        public readonly ?string $title = null,
    ) {}
}
```

### Providers et capacités

| Provider | `isbn` | `title` (non-manga) | `title` (manga) | `enrich` |
|----------|:------:|:-------------------:|:---------------:|:--------:|
| `GoogleBooksLookup` | oui | oui | oui | — |
| `OpenLibraryLookup` | oui | — | — | — |
| `AniListLookup` | — | — | oui | — |
| `GeminiLookup` | oui | oui | oui | oui |

### Structure fichiers

```
src/Service/Lookup/
  LookupProviderInterface.php
  EnrichableLookupProviderInterface.php
  LookupResult.php
  GoogleBooksLookup.php
  OpenLibraryLookup.php
  AniListLookup.php
  GeminiLookup.php
  LookupOrchestrator.php
```

### `GeminiLookup`

- Package : `google-gemini-php/symfony` v2.0
- Modèle : Gemini 2.0 Flash (meilleur rapport quotas/qualité)
- **Structured output** : JSON schema forçant le format `LookupResult`
- **Google Search grounding** : recherche web pour compléter les infos
- **Cache** : Symfony Cache filesystem, clé = `gemini_` + md5(query+type+mode), TTL 30 jours
- **Rate limiting** : compteur via cache (clé par minute + clé par jour), limites configurables

### `LookupOrchestrator`

Remplace `IsbnLookupService`. Flux :

1. Filtre les providers via `supports($mode, $type)`
2. Appelle chaque provider (parallèle via HttpClient lazy pour ceux qui le supportent)
3. Merge les `LookupResult` avec règles de priorité :
   - Google Books prioritaire sur Open Library
   - AniList override thumbnail et isOneShot pour les mangas
   - Gemini complète les champs restants
4. Si résultat incomplet + provider `EnrichableLookupProviderInterface` → enrichit
5. Collecte les `apiMessages` de chaque provider

### Impact

- `ApiController` injecte `LookupOrchestrator` au lieu de `IsbnLookupService`
- Tests existants migrés vers services individuels + tests orchestrateur
- Clé API Gemini dans `.env.local` : `GEMINI_API_KEY=...`

## Choix techniques

- **google-gemini-php/symfony** plutôt que HTTP brut : DI intégré, fake client pour tests, structured output natif
- **Cache filesystem** plutôt que BDD : simple, suffisant pour dev solo, TTL natif
- **Gemini 2.0 Flash** : 10 RPM / 250 RPD en plan gratuit, suffisant pour l'usage
- **Pas d'interface unifiée pour le merge** : les règles de priorité sont spécifiques (AniList override thumbnail pour manga), un merge générique serait trop abstrait
