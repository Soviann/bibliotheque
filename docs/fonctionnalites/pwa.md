# Mode PWA (Progressive Web App)

Ma Bibliotheque BD est une PWA : elle peut être installée sur votre appareil et fonctionne même hors-ligne.

---

## Qu'est-ce qu'une PWA ?

Une Progressive Web App combine le meilleur du web et des applications natives :

| Avantage | Description |
|----------|-------------|
| **Installable** | Ajoutez-la à votre écran d'accueil comme une app |
| **Hors-ligne** | Consultez votre collection sans connexion |
| **Rapide** | Les ressources sont mises en cache localement |
| **À jour** | Se met à jour automatiquement |

---

## Installation

### Sur mobile (Android/iOS)

1. Ouvrez l'application dans votre navigateur
2. Appuyez sur le bouton **Installer** (ou "Ajouter à l'écran d'accueil")
3. Confirmez l'installation
4. L'application apparaît sur votre écran d'accueil

### Sur desktop (Chrome, Edge)

1. Ouvrez l'application
2. Cliquez sur l'icône d'installation dans la barre d'adresse
3. Confirmez l'installation
4. L'application s'ouvre dans sa propre fenêtre

---

## Mode hors-ligne

### Ce qui fonctionne hors-ligne

| Fonctionnalité | Disponibilité |
|----------------|---------------|
| Consulter la bibliothèque | Oui (données en cache) |
| Voir les détails d'une série | Oui (si déjà visitée) |
| Voir les couvertures | Oui (si déjà chargées) |
| Filtrer et rechercher | Oui |

### Ce qui nécessite une connexion

| Fonctionnalité | Raison |
|----------------|--------|
| Ajouter une série | Écriture en BDD |
| Modifier une série | Écriture en BDD |
| Recherche ISBN/titre | Appels API externes |
| Upload de couverture | Envoi de fichier |

### Indicateur de connexion

Un indicateur visuel s'affiche quand vous êtes hors-ligne :
- **En ligne** : Fonctionnement normal
- **Hors-ligne** : Message d'avertissement, fonctionnalités limitées

---

## Stratégies de cache

Le Service Worker utilise différentes stratégies selon le type de ressource :

### Assets (CSS, JS, images)

| Stratégie | `CacheFirst` |
|-----------|--------------|
| Cache | `bibliotheque-assets` |
| Durée | 1 an |
| Comportement | Sert depuis le cache, vérifie les mises à jour en arrière-plan |

### Pages HTML

| Stratégie | `NetworkFirst` |
|-----------|----------------|
| Cache | `bibliotheque-pages` |
| Timeout | 3 secondes |
| Comportement | Essaie le réseau d'abord, sert le cache si hors-ligne |

### API

| Stratégie | `NetworkFirst` |
|-----------|----------------|
| Cache | `bibliotheque-api` |
| Timeout | 3 secondes |
| Comportement | Données fraîches si possible, cache sinon |

### Google Fonts

| Stratégie | `CacheFirst` (webfonts), `StaleWhileRevalidate` (stylesheets) |
|-----------|-------------------------------------------------------------|
| Cache | `google-fonts-*` |
| Comportement | Fonts persistantes, styles mis à jour en arrière-plan |

### Images de couverture

| Stratégie | `CacheFirst` |
|-----------|--------------|
| Cache | `bibliotheque-images` |
| Comportement | Images mises en cache après premier chargement |

---

## Page de fallback

Quand une page non mise en cache est demandée hors-ligne :

1. Le Service Worker détecte l'absence de réseau
2. La page `/offline` est affichée
3. Cette page propose :
   - Un message explicatif
   - Un bouton pour réessayer
   - Un lien vers l'accueil (en cache)

---

## Mise à jour

### Mise à jour automatique

1. À chaque visite, le navigateur vérifie les mises à jour du Service Worker
2. Si une nouvelle version existe, elle est téléchargée en arrière-plan
3. Au prochain rechargement, la nouvelle version s'active

### Forcer une mise à jour

Pour forcer la mise à jour du cache :

1. Ouvrez les DevTools (F12)
2. Onglet Application > Service Workers
3. Cliquez sur "Update" ou "Unregister"
4. Rechargez la page

---

## Configuration technique

### Manifest

Le manifest est généré automatiquement depuis `config/packages/pwa.yaml` :

```yaml
pwa:
    manifest:
        name: Ma Bibliotheque BD
        short_name: BibliotheQue
        description: Gestion de ma collection de BD et comics
        theme_color: '#1976d2'
        background_color: '#ffffff'
        display: standalone
        start_url: /
        lang: fr
```

### Service Worker

Généré par `spomky-labs/pwa-bundle` avec Workbox :

- Fichier : `/sw.js`
- Scope : `/`
- Skip waiting : activé (mise à jour immédiate)

### Icônes

| Taille | Usage |
|--------|-------|
| 192x192 | Icône d'application |
| 512x512 | Splash screen, partage |

---

## Dépannage

### L'application ne s'installe pas

1. Vérifiez que vous êtes en HTTPS
2. Vérifiez que le manifest est accessible (`/manifest.webmanifest`)
3. Ouvrez DevTools > Application > Manifest pour voir les erreurs

### Le cache ne se met pas à jour

1. Ouvrez DevTools > Application > Storage
2. Cliquez sur "Clear site data"
3. Rechargez la page

### L'application affiche des données obsolètes

1. Forcez le rafraîchissement : Ctrl+Shift+R (ou Cmd+Shift+R sur Mac)
2. Ou videz le cache comme ci-dessus

### Le mode hors-ligne ne fonctionne pas

1. Vérifiez que le Service Worker est actif (DevTools > Application > Service Workers)
2. Naviguez sur les pages que vous voulez mettre en cache (elles doivent être visitées au moins une fois)

---

## API disponible hors-ligne

L'endpoint `/api/comics` est mis en cache et disponible hors-ligne :

```
GET /api/comics
```

Retourne toutes les séries avec leurs métadonnées, permettant à l'interface de fonctionner sans connexion.

---

## Étapes suivantes

- [Architecture technique](../architecture/README.md) - Fonctionnement interne
- [Guide de développement](../developpement/README.md) - Contribuer au projet
