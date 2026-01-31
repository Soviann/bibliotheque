# Fonctionnalités

Ma Bibliotheque BD offre un ensemble complet de fonctionnalités pour gérer votre collection de BD, Comics, Mangas et Livres.

---

## Vue d'ensemble

### Gestion de collection

L'application permet de gérer une bibliothèque complète avec :

- **4 types de publications** : BD, Comics, Manga, Livre
- **Suivi détaillé par tome** : achat, téléchargement, stockage NAS
- **Statuts de collection** : En cours d'achat, Terminé, Arrêté
- **Liste de souhaits** : Sépare les séries possédées des séries convoitées

[Guide complet : Gestion de collection](gestion-collection.md)

### Recherche automatique

Gagnez du temps avec le préremplissage automatique :

- **Recherche par ISBN** : Scannez ou saisissez l'ISBN d'un tome
- **Recherche par titre** : Trouvez une série par son nom
- **3 APIs intégrées** : Google Books, Open Library, AniList
- **Détection automatique** : Type, auteurs, éditeur, couverture

[Guide complet : Recherche ISBN](recherche-isbn.md)

### Mode PWA

Accédez à votre bibliothèque partout :

- **Installation mobile** : Ajoutez l'application à votre écran d'accueil
- **Mode hors-ligne** : Consultez votre collection sans connexion
- **Synchronisation** : Les données se mettent à jour automatiquement

[Guide complet : Mode PWA](pwa.md)

---

## Fonctionnalités par écran

### Page d'accueil (Bibliothèque)

| Fonctionnalité | Description |
|----------------|-------------|
| Liste des séries | Affichage en cartes avec couvertures |
| Filtres | Par type, statut, présence NAS |
| Recherche | Par titre ou ISBN |
| Tri | Alphabétique, date de modification |
| Badges | Indicateurs visuels de progression |

### Page de détail

| Fonctionnalité | Description |
|----------------|-------------|
| Informations complètes | Titre, auteurs, éditeur, description |
| Couverture | Image uploadée ou récupérée via API |
| Statistiques | Progression de la collection |
| Grille des tomes | Vue détaillée de chaque tome |
| Actions | Modifier, supprimer |

### Formulaire de création/édition

| Fonctionnalité | Description |
|----------------|-------------|
| Recherche ISBN | Préremplissage automatique |
| Recherche titre | Préremplissage automatique |
| Autocomplétion auteurs | Suggestions basées sur la BDD |
| Upload couverture | Drag & drop d'images |
| Gestion des tomes | Ajout/suppression dynamique |

### Liste de souhaits

| Fonctionnalité | Description |
|----------------|-------------|
| Séries souhaitées | Séparées de la collection principale |
| Transfert | Bouton pour passer en bibliothèque |
| Même gestion | Filtres, tri, recherche |

---

## Types de publications

### BD (Bande dessinée)

Albums franco-belges traditionnels :
- Format album cartonné
- Numérotation classique (Tome 1, 2, 3...)
- Exemples : Astérix, Tintin, Blake et Mortimer

### Comics

Publications américaines :
- Super-héros, graphic novels
- Numérotation variable (issues, volumes)
- Exemples : Batman, Spider-Man, Walking Dead

### Manga

Publications japonaises :
- Sens de lecture inversé
- Numérotation japonaise
- Exemples : One Piece, Naruto, Dragon Ball

### Livre

Romans, essais, beaux livres :
- Souvent des volumes uniques (one-shots)
- Intégrales, artbooks
- Exemples : Les Misérables, artbooks

---

## Statuts de collection

| Statut | Icône | Description |
|--------|-------|-------------|
| **En cours d'achat** | - | Série en cours de publication, vous l'achetez régulièrement |
| **Terminé** | - | Série terminée et collection complète |
| **Arrêté** | - | Vous avez arrêté d'acheter cette série |
| **Liste de souhaits** | - | Série que vous aimeriez posséder |

---

## Suivi des tomes

Chaque tome peut être marqué avec :

| Attribut | Description |
|----------|-------------|
| **Acheté** | Vous possédez physiquement ce tome |
| **Téléchargé** | Vous avez la version numérique |
| **Sur NAS** | Stocké sur votre serveur de fichiers |
| **ISBN** | Code-barres du livre (pour recherche) |
| **Titre** | Titre spécifique si différent de la série |

---

## One-shots

Les one-shots sont des publications en volume unique :

- **Détection automatique** : Via Google Books et AniList
- **Création simplifiée** : Un seul tome créé automatiquement
- **Affichage adapté** : Badge "Tome unique", pas de grille de tomes

Exemples : intégrales, artbooks, romans graphiques autonomes.

---

## Étapes suivantes

- [Gestion de collection](gestion-collection.md) - Guide détaillé
- [Recherche ISBN](recherche-isbn.md) - APIs et préremplissage
- [Mode PWA](pwa.md) - Installation et mode hors-ligne
