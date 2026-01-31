# Entités Doctrine

Ce guide documente le modèle de données de l'application.

---

## Diagramme des relations

```
┌─────────────────────────────────────────────────────────────────┐
│                                                                 │
│  ┌─────────────┐       ManyToMany       ┌─────────────┐        │
│  │   Author    │◄──────────────────────►│ ComicSeries │        │
│  │             │                        │             │        │
│  │ - name      │                        │ - title     │        │
│  │             │                        │ - type      │        │
│  └─────────────┘                        │ - status    │        │
│                                         │ - ...       │        │
│                                         │             │        │
│                                         └──────┬──────┘        │
│                                                │               │
│                                                │ OneToMany     │
│                                                │               │
│                                                ▼               │
│                                         ┌─────────────┐        │
│                                         │    Tome     │        │
│                                         │             │        │
│                                         │ - number    │        │
│                                         │ - bought    │        │
│                                         │ - downloaded│        │
│                                         │ - onNas     │        │
│                                         │ - isbn      │        │
│                                         └─────────────┘        │
│                                                                 │
│  ┌─────────────┐                                               │
│  │    User     │  (indépendant, authentification)              │
│  │             │                                               │
│  │ - email     │                                               │
│  │ - password  │                                               │
│  │ - roles     │                                               │
│  └─────────────┘                                               │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## ComicSeries

Entité principale représentant une série BD/Comics/Manga/Livre.

**Fichier** : `src/Entity/ComicSeries.php`

### Propriétés

| Propriété | Type | Nullable | Description |
|-----------|------|:--------:|-------------|
| `id` | int | Non | Identifiant auto-incrémenté |
| `title` | string(255) | Non | Titre de la série |
| `type` | ComicType | Non | Type (BD, Comics, Manga, Livre) |
| `status` | ComicStatus | Non | Statut de la collection |
| `isOneShot` | bool | Non | Volume unique (intégrale, one-shot) |
| `isWishlist` | bool | Non | Dans la liste de souhaits |
| `latestPublishedIssue` | int | Oui | Dernier numéro paru chez l'éditeur |
| `latestPublishedIssueComplete` | bool | Non | Série terminée par l'éditeur |
| `description` | text | Oui | Synopsis, résumé |
| `publishedDate` | string | Oui | Date de première publication |
| `publisher` | string | Oui | Éditeur |
| `coverImage` | string | Oui | Nom du fichier image uploadé |
| `coverUrl` | string | Oui | URL externe de la couverture |

### Relations

| Relation | Type | Entité cible | Description |
|----------|------|--------------|-------------|
| `authors` | ManyToMany | Author | Auteurs de la série |
| `tomes` | OneToMany | Tome | Volumes de la série |

Configuration des relations :

```php
// Relation auteurs (bidirectionnelle)
#[ORM\ManyToMany(targetEntity: Author::class, inversedBy: 'comicSeries')]
private Collection $authors;

// Relation tomes (cascade, orphanRemoval)
#[ORM\OneToMany(targetEntity: Tome::class, mappedBy: 'comicSeries', cascade: ['persist', 'remove'], orphanRemoval: true)]
#[ORM\OrderBy(['number' => 'ASC'])]
private Collection $tomes;
```

### Méthodes métier

| Méthode | Retour | Description |
|---------|--------|-------------|
| `getCurrentIssue()` | int\|null | Numéro max des tomes possédés |
| `getLastBought()` | int\|null | Numéro du dernier tome acheté |
| `getLastDownloaded()` | int\|null | Numéro du dernier tome téléchargé |
| `getOwnedTomesNumbers()` | array | Liste des numéros possédés |
| `getMissingTomesNumbers()` | array | Numéros manquants (1 à latestPublishedIssue) |
| `isCurrentIssueComplete()` | bool | currentIssue == latestPublishedIssue |
| `isLastBoughtComplete()` | bool | lastBought == latestPublishedIssue |
| `isLastDownloadedComplete()` | bool | lastDownloaded == latestPublishedIssue |
| `isOnNas()` | bool | Au moins un tome sur NAS |

### Exemple d'utilisation

```php
$series = new ComicSeries();
$series->setTitle('One Piece');
$series->setType(ComicType::MANGA);
$series->setStatus(ComicStatus::BUYING);
$series->setLatestPublishedIssue(107);

// Ajouter un tome
$tome = new Tome();
$tome->setNumber(1);
$tome->setBought(true);
$series->addTome($tome);

// Calculer la progression
$current = $series->getCurrentIssue(); // 1
$missing = $series->getMissingTomesNumbers(); // [2, 3, ..., 107]
```

---

## Tome

Volume individuel d'une série.

**Fichier** : `src/Entity/Tome.php`

### Propriétés

| Propriété | Type | Nullable | Description |
|-----------|------|:--------:|-------------|
| `id` | int | Non | Identifiant auto-incrémenté |
| `number` | int | Non | Numéro du tome (≥ 0) |
| `bought` | bool | Non | Tome acheté (possession physique) |
| `downloaded` | bool | Non | Tome téléchargé (version numérique) |
| `onNas` | bool | Non | Stocké sur le NAS |
| `isbn` | string(20) | Oui | ISBN-10 ou ISBN-13 |
| `title` | string(255) | Oui | Titre spécifique (si différent de la série) |

### Relations

| Relation | Type | Entité cible | Description |
|----------|------|--------------|-------------|
| `comicSeries` | ManyToOne | ComicSeries | Série parente |

### Contraintes

- `number` doit être ≥ 0 (permet les hors-série numérotés 0)
- La combinaison `comicSeries` + `number` devrait être unique (non contraint en BDD)

### Exemple d'utilisation

```php
$tome = new Tome();
$tome->setNumber(1);
$tome->setBought(true);
$tome->setDownloaded(true);
$tome->setOnNas(false);
$tome->setIsbn('9782723456789');

$series->addTome($tome);
```

---

## Author

Auteur d'une ou plusieurs séries.

**Fichier** : `src/Entity/Author.php`

### Propriétés

| Propriété | Type | Nullable | Description |
|-----------|------|:--------:|-------------|
| `id` | int | Non | Identifiant auto-incrémenté |
| `name` | string(255) | Non | Nom de l'auteur (unique) |

### Relations

| Relation | Type | Entité cible | Description |
|----------|------|--------------|-------------|
| `comicSeries` | ManyToMany | ComicSeries | Séries de l'auteur |

### Contrainte d'unicité

Le nom est unique en base de données, ce qui permet de réutiliser les auteurs entre séries.

### Exemple d'utilisation

```php
// Via le repository (recommandé)
$author = $authorRepository->findOrCreate('Eiichiro Oda');

// Ou création directe
$author = new Author();
$author->setName('Eiichiro Oda');

$series->addAuthor($author);
```

---

## User

Utilisateur pour l'authentification.

**Fichier** : `src/Entity/User.php`

### Propriétés

| Propriété | Type | Nullable | Description |
|-----------|------|:--------:|-------------|
| `id` | int | Non | Identifiant auto-incrémenté |
| `email` | string(180) | Non | Email (identifiant de connexion, unique) |
| `password` | string | Non | Mot de passe hashé |
| `roles` | array | Non | Rôles (ROLE_USER inclus par défaut) |

### Interfaces implémentées

- `UserInterface` : méthodes d'authentification Symfony
- `PasswordAuthenticatedUserInterface` : authentification par mot de passe

### Méthodes

| Méthode | Description |
|---------|-------------|
| `getUserIdentifier()` | Retourne l'email |
| `getRoles()` | Retourne les rôles + ROLE_USER |
| `eraseCredentials()` | Efface les données sensibles temporaires |

### Exemple de création

```bash
# Via la commande console
ddev exec bin/console app:create-user email@example.com motdepasse
```

---

## Enums

### ComicType

**Fichier** : `src/Enum/ComicType.php`

```php
enum ComicType: string
{
    case BD = 'bd';
    case COMICS = 'comics';
    case LIVRE = 'livre';
    case MANGA = 'manga';

    public function getLabel(): string
    {
        return match($this) {
            self::BD => 'BD',
            self::COMICS => 'Comics',
            self::LIVRE => 'Livre',
            self::MANGA => 'Manga',
        };
    }
}
```

### ComicStatus

**Fichier** : `src/Enum/ComicStatus.php`

```php
enum ComicStatus: string
{
    case BUYING = 'buying';
    case FINISHED = 'finished';
    case STOPPED = 'stopped';
    case WISHLIST = 'wishlist';

    public function getLabel(): string
    {
        return match($this) {
            self::BUYING => 'En cours d\'achat',
            self::FINISHED => 'Terminée',
            self::STOPPED => 'Arrêtée',
            self::WISHLIST => 'Liste de souhaits',
        };
    }
}
```

---

## Migrations

Les migrations sont générées automatiquement via Doctrine :

```bash
# Après modification d'une entité
ddev exec bin/console doctrine:migrations:diff -n

# Appliquer les migrations
ddev exec bin/console doctrine:migrations:migrate -n
```

Les fichiers de migration se trouvent dans `migrations/`.

---

## Étapes suivantes

- [Services](services.md) - Services métier
- [Architecture](README.md) - Vue d'ensemble
