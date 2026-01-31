# API REST

Ce guide documente les endpoints API de l'application.

---

## Vue d'ensemble

L'application expose des endpoints JSON pour :
- Récupérer la liste des séries (PWA/offline)
- Rechercher par ISBN
- Rechercher par titre

Tous les endpoints nécessitent une authentification.

---

## Endpoints

### GET /api/comics

Retourne toutes les séries de la bibliothèque.

**Usage** : Mode PWA/offline, applications tierces.

**Authentification** : Requise

**Paramètres** : Aucun

**Réponse** :

```json
[
  {
    "id": 1,
    "title": "One Piece",
    "type": "manga",
    "status": "buying",
    "isOneShot": false,
    "latestPublishedIssue": 107,
    "latestPublishedIssueComplete": false,
    "currentIssue": 105,
    "lastBought": 105,
    "lastDownloaded": 100,
    "description": "Luffy rêve de devenir le roi des pirates...",
    "publisher": "Glénat",
    "publishedDate": "1997-12-24",
    "coverUrl": "https://...",
    "authors": ["Eiichiro Oda"],
    "ownedTomesNumbers": [1, 2, 3, ..., 105],
    "missingTomesNumbers": [106, 107],
    "isOnNas": true
  },
  // ...
]
```

**Champs calculés** :
| Champ | Description |
|-------|-------------|
| `currentIssue` | Numéro max des tomes possédés |
| `lastBought` | Numéro du dernier tome acheté |
| `lastDownloaded` | Numéro du dernier tome téléchargé |
| `ownedTomesNumbers` | Liste des numéros possédés |
| `missingTomesNumbers` | Numéros entre 1 et `latestPublishedIssue` non possédés |
| `isOnNas` | Au moins un tome est sur le NAS |

---

### GET /api/isbn-lookup

Recherche des informations bibliographiques par ISBN.

**Usage** : Préremplissage du formulaire de création/édition.

**Authentification** : Requise

**Paramètres** :

| Paramètre | Type | Requis | Description |
|-----------|------|:------:|-------------|
| `isbn` | string | Oui | ISBN-10 ou ISBN-13 |
| `type` | string | Non | `manga`, `bd`, `comics`, `livre` |

**Exemple** :
```
GET /api/isbn-lookup?isbn=9782723456789&type=manga
```

**Réponse (succès)** :

```json
{
  "title": "One Piece",
  "authors": ["Eiichiro Oda"],
  "description": "Luffy rêve de devenir le roi des pirates...",
  "publishedDate": "1997-12-24",
  "publisher": "Glénat",
  "isbn": "9782723456789",
  "thumbnail": "https://books.google.com/...",
  "isOneShot": false,
  "type": "manga",
  "sources": ["Google Books", "AniList"]
}
```

**Réponse (non trouvé)** :

```json
{
  "error": "Not found"
}
```

**Code HTTP** : `404 Not Found`

**Champs retournés** :

| Champ | Type | Nullable | Description |
|-------|------|:--------:|-------------|
| `title` | string | Non | Titre du livre/série |
| `authors` | array | Oui | Liste des auteurs |
| `description` | string | Oui | Synopsis/résumé |
| `publishedDate` | string | Oui | Date de publication |
| `publisher` | string | Oui | Éditeur |
| `isbn` | string | Oui | ISBN normalisé |
| `thumbnail` | string | Oui | URL de la couverture |
| `isOneShot` | bool | Non | Volume unique détecté |
| `type` | string | Oui | Type déduit (`manga`, `bd`, `comics`, `livre`) |
| `sources` | array | Non | APIs utilisées |

---

### GET /api/title-lookup

Recherche des informations bibliographiques par titre.

**Usage** : Préremplissage du formulaire quand l'ISBN n'est pas connu.

**Authentification** : Requise

**Paramètres** :

| Paramètre | Type | Requis | Description |
|-----------|------|:------:|-------------|
| `title` | string | Oui | Titre à rechercher |
| `type` | string | Non | `manga`, `bd`, `comics`, `livre` |

**Exemple** :
```
GET /api/title-lookup?title=Attack%20on%20Titan&type=manga
```

**Réponse** : Même format que `/api/isbn-lookup`

**Comportement** :
- Si `type=manga` : recherche prioritaire sur AniList puis Google Books
- Sinon : recherche sur Google Books uniquement

---

## Codes HTTP

| Code | Description |
|------|-------------|
| `200 OK` | Requête réussie |
| `401 Unauthorized` | Authentification requise |
| `404 Not Found` | Ressource non trouvée |
| `500 Internal Server Error` | Erreur serveur |

---

## Cache

### Service Worker

Les réponses API sont mises en cache par le Service Worker :

| Endpoint | Stratégie | Cache | Timeout |
|----------|-----------|-------|---------|
| `/api/*` | NetworkFirst | `bibliotheque-api` | 3s |

**Comportement** :
1. Essaie le réseau pendant 3 secondes
2. Si échec ou timeout, sert depuis le cache
3. Met à jour le cache après chaque réponse réseau réussie

### Headers

Les réponses n'incluent pas de headers de cache HTTP car le Service Worker gère la mise en cache.

---

## Utilisation avec JavaScript

### Fetch API

```javascript
async function getComics() {
  const response = await fetch('/api/comics', {
    credentials: 'same-origin', // Inclut les cookies de session
  });

  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }

  return response.json();
}

async function lookupIsbn(isbn, type = null) {
  const params = new URLSearchParams({ isbn });
  if (type) params.append('type', type);

  const response = await fetch(`/api/isbn-lookup?${params}`, {
    credentials: 'same-origin',
  });

  if (response.status === 404) {
    return null;
  }

  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }

  return response.json();
}
```

### Contrôleur Stimulus

L'application utilise `comic_form_controller.js` pour les recherches :

```javascript
// assets/controllers/comic_form_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['isbn', 'title', 'type', /* ... */];

  async lookupIsbn() {
    const isbn = this.isbnTarget.value;
    const type = this.typeTarget.value;

    const response = await fetch(
      `/api/isbn-lookup?isbn=${encodeURIComponent(isbn)}&type=${type}`
    );

    if (response.ok) {
      const data = await response.json();
      this.fillForm(data);
    }
  }

  fillForm(data) {
    if (data.title) this.titleTarget.value = data.title;
    // ...
  }
}
```

---

## Sécurité

### Authentification

Tous les endpoints requièrent une session authentifiée :

```yaml
# config/packages/security.yaml
access_control:
    - { path: ^/api, roles: ROLE_USER }
```

### CSRF

Les endpoints GET ne nécessitent pas de token CSRF.

Pour les futurs endpoints POST/PUT/DELETE, incluez le token :

```javascript
const response = await fetch('/api/endpoint', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
  },
  body: JSON.stringify(data),
});
```

---

## Limites et quotas

### APIs externes

Les APIs de recherche (Google Books, AniList) ont des limites :

| API | Limite |
|-----|--------|
| Google Books | Pas de limite documentée pour usage normal |
| AniList | 90 requêtes/minute |
| Open Library | Pas de limite documentée |

L'application n'implémente pas de rate limiting côté serveur.

---

## Étapes suivantes

- [Architecture](../architecture/README.md) - Vue technique
- [Guide de développement](../developpement/README.md) - Contribuer
