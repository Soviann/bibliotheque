# Recherche ISBN et titre

L'application peut préremplir automatiquement les informations d'une série en recherchant par ISBN ou par titre.

---

## Vue d'ensemble

Trois APIs sont interrogées pour enrichir les données :

| API | Type | Données fournies |
|-----|------|------------------|
| **Google Books** | ISBN, Titre | Titre, auteurs, éditeur, date, description, couverture |
| **Open Library** | ISBN | Auteurs, éditeur (enrichissement) |
| **AniList** | Titre (mangas) | Couverture HD, détection one-shot |
| **Wikipedia/Wikidata** | ISBN, Titre | Titre, auteurs, éditeur, date, couverture, description |
| **BnF** | ISBN, Titre | Titre, auteurs, éditeur, date, ISBN (catalogue national français) |
| **Gemini** | ISBN, Titre | Enrichissement IA (titre, auteurs, éditeur, description) |

---

## Recherche par ISBN

### Utilisation

1. Sélectionnez d'abord le **type** de publication
2. Saisissez l'ISBN dans le champ dédié (avec ou sans tirets)
3. Cliquez sur le bouton de recherche (icône loupe)
4. Les champs sont préremplis automatiquement

### Formats ISBN acceptés

| Format | Exemple |
|--------|---------|
| ISBN-13 | 9782800152851 |
| ISBN-13 avec tirets | 978-2-8001-5285-1 |
| ISBN-10 | 2800152850 |
| ISBN-10 avec tirets | 2-8001-5285-0 |

### Champs préremplis

- Titre de la série
- Auteurs (avec création automatique)
- Éditeur
- Date de publication
- Description
- URL de couverture
- Type (déduit de l'éditeur ou d'AniList)
- One-shot (si détecté)
- ISBN du tome (si création d'un one-shot)

---

## Recherche par titre

### Utilisation

1. Sélectionnez d'abord le **type** de publication
2. Saisissez le titre de la série
3. Cliquez sur le bouton de recherche à côté du champ titre
4. Les champs sont préremplis automatiquement

### Comportement selon le type

| Type | API utilisée |
|------|--------------|
| Manga | AniList (GraphQL) + Google Books |
| BD, Comics, Livre | Google Books |

---

## Détection automatique du type

Quand l'ISBN est recherché, le type peut être déduit :

### Via AniList

Si un manga est trouvé sur AniList, le type est automatiquement défini sur "Manga".

### Via l'éditeur

Certains éditeurs sont associés à des types :

| Éditeur | Type déduit |
|---------|-------------|
| Kana, Pika, Glénat Manga, Ki-oon... | Manga |
| Dargaud, Dupuis, Le Lombard... | BD |
| Urban Comics, Panini Comics... | Comics |

---

## Détection des one-shots

### Via Google Books

Si le résultat Google Books **n'a pas** de champ `seriesInfo`, le livre est considéré comme un volume unique (one-shot).

### Via AniList

Un manga est détecté comme one-shot si :
- Le champ `format` vaut `ONE_SHOT`
- **OU** `volumes = 1` **ET** `status = FINISHED`

### Comportement

Quand un one-shot est détecté :
1. La case "One-shot" est cochée automatiquement
2. Un tome avec le numéro 1 est créé
3. L'ISBN est pré-rempli dans ce tome
4. "Dernier tome paru" est défini à 1
5. "Série terminée" est coché

---

## Fusion des résultats

Les trois APIs sont interrogées et les résultats fusionnés :

### Priorité des champs

| Champ | Source prioritaire |
|-------|-------------------|
| Titre | Google Books |
| Auteurs | Google Books, puis Open Library |
| Éditeur | Google Books, puis Open Library |
| Description | Google Books |
| Couverture | AniList (HD), puis Google Books |
| Type | AniList, puis déduction par éditeur |
| One-shot | AniList, puis Google Books |

### Sources multiples

Le message flash affiche les sources utilisées :

```
Champs préremplis : titre, auteurs, éditeur, couverture
Sources : Google Books, AniList
```

---

## API technique

### Endpoint recherche ISBN

```
GET /api/isbn-lookup?isbn={isbn}&type={type}
```

Paramètres :
| Paramètre | Type | Description |
|-----------|------|-------------|
| `isbn` | string | ISBN-10 ou ISBN-13 |
| `type` | string | `manga`, `bd`, `comics`, `livre` (optionnel) |

Réponse :
```json
{
  "title": "One Piece",
  "authors": ["Eiichiro Oda"],
  "publisher": "Glénat",
  "publishedDate": "1997-12-24",
  "description": "Luffy rêve de devenir...",
  "thumbnail": "https://...",
  "isbn": "9782723456789",
  "isOneShot": false,
  "type": "manga",
  "sources": ["Google Books", "AniList"]
}
```

### Endpoint recherche titre

```
GET /api/title-lookup?title={title}&type={type}
```

Paramètres :
| Paramètre | Type | Description |
|-----------|------|-------------|
| `title` | string | Titre à rechercher |
| `type` | string | `manga`, `bd`, `comics`, `livre` (recommandé) |

Réponse : même format que la recherche ISBN.

---

## Nettoyage des titres

Pour la recherche AniList, le titre est nettoyé automatiquement :

| Pattern supprimé | Exemple |
|-----------------|---------|
| `Tome X` | "One Piece Tome 1" → "One Piece" |
| `Vol. X` | "Naruto Vol. 5" → "Naruto" |
| `Volume X` | "Dragon Ball Volume 10" → "Dragon Ball" |
| `T.X` | "Astérix T.35" → "Astérix" |
| `#X` | "Spider-Man #100" → "Spider-Man" |

---

## Cas d'usage

### Ajouter un manga avec ISBN

1. Type : **Manga**
2. ISBN : `9782344001851`
3. Cliquez sur recherche
4. Résultat : Dragon Ball (Glénat), couverture HD depuis AniList

### Ajouter un one-shot

1. Type : **Livre**
2. ISBN : `9782070423208`
3. Cliquez sur recherche
4. Résultat : Intégrale détectée comme one-shot, tome 1 créé

### Ajouter une série par titre

1. Type : **Manga**
2. Titre : `Attack on Titan`
3. Cliquez sur recherche titre
4. Résultat : L'Attaque des Titans (AniList + Google Books)

---

## Dépannage

| Problème | Solution |
|----------|----------|
| Aucun résultat | Vérifiez l'ISBN, essayez la recherche par titre |
| Mauvaise couverture | Uploadez manuellement une image |
| Type incorrect | Modifiez-le avant la recherche |
| Auteurs manquants | Ajoutez-les manuellement |

---

## Étapes suivantes

- [Gestion de collection](gestion-collection.md) - Créer et gérer vos séries
- [Mode PWA](pwa.md) - Accès hors-ligne
