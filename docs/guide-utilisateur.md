# Guide utilisateur

## Accès et connexion

L'application est une **Progressive Web App** (PWA) accessible depuis un navigateur web. Seuls les utilisateurs disposant d'un compte peuvent y accéder.

### Se connecter

1. Ouvrir l'URL de l'application dans un navigateur
2. Saisir votre adresse email et votre mot de passe
3. Cliquer sur **Se connecter**

La session reste active pendant **30 jours** grâce au token JWT, ce qui permet une utilisation hors-ligne de la PWA.

### Se déconnecter

Cliquer sur **Déconnexion** dans la barre de navigation (header desktop ou menu mobile).

---

## Navigation

L'interface s'adapte à la taille de l'écran :

- **Mobile** : barre de navigation en bas de l'écran avec 5 onglets
- **Desktop** : barre de navigation en haut avec les mêmes liens

| Onglet | Description |
|--------|-------------|
| Accueil | Bibliothèque complète |
| Wishlist | Liste de souhaits |
| Ajouter | Formulaire de création |
| Recherche | Recherche dans la collection |
| Corbeille | Séries supprimées |

---

## Bibliothèque (page d'accueil)

La page d'accueil affiche toutes les séries de la collection sous forme de grille de cartes.

### Cartes de série

Chaque carte affiche :
- La couverture (ou un placeholder si absente)
- Le titre
- Le(s) auteur(s)
- Le type (BD, Manga, Comics, Roman, Webtoon)
- Le nombre de tomes (ou « One-shot »)
- Le statut (En cours d'achat, Complet, En pause, Abandonné, Liste de souhaits)

Cliquer sur une carte ouvre la page de détail de la série.

### Filtres

Deux menus déroulants permettent de filtrer la bibliothèque :

- **Type** : BD, Manga, Comics, Roman, Webtoon
- **Statut** : En cours d'achat, Complet, En pause, Abandonné, Liste de souhaits

---

## Wishlist

Affiche uniquement les séries avec le statut **Liste de souhaits**. L'interface est identique à la bibliothèque avec les mêmes filtres.

---

## Recherche

Permet de rechercher dans la collection par titre. La recherche se déclenche automatiquement après saisie (avec un délai anti-rebond de 300 ms).

---

## Détail d'une série

La page de détail affiche toutes les informations d'une série :

- Couverture en grand format
- Titre, auteur(s), éditeur
- Type et statut
- Description
- Date de publication
- Nombre de tomes publiés

### Gestion des tomes

Si la série n'est pas un one-shot, la liste des tomes s'affiche avec pour chaque tome :
- Numéro
- Titre (optionnel)
- ISBN (optionnel)
- Indicateurs : Acheté, Téléchargé, Sur le NAS, Lu

### Actions disponibles

- **Modifier** : ouvre le formulaire d'édition
- **Supprimer** : déplace la série dans la corbeille (suppression douce)

---

## Ajouter / modifier une série

Le formulaire de création/édition est la page la plus riche de l'application.

### Recherche automatique (Lookup)

Avant de remplir manuellement, vous pouvez utiliser la recherche automatique :

1. **Par ISBN** : saisir un ISBN-10 ou ISBN-13, l'application interroge plusieurs APIs (Google Books, BnF, OpenLibrary, AniList, Wikipedia, Gemini) pour pré-remplir les champs
2. **Par titre** : saisir un titre et l'application recherche les informations correspondantes
3. **Par scan de code-barres** : utiliser la caméra du téléphone pour scanner le code-barres d'un livre/manga

Les champs pré-remplis peuvent être modifiés avant la sauvegarde.

### Champs du formulaire

| Champ | Description | Obligatoire |
|-------|-------------|:-----------:|
| Titre | Nom de la série | Oui |
| Type | BD, Manga, Comics, Roman, Webtoon | Oui |
| Statut | En cours d'achat, Complet, etc. | Oui |
| Auteur(s) | Un ou plusieurs auteurs (auto-complétion) | Non |
| Éditeur | Maison d'édition | Non |
| Description | Résumé ou notes | Non |
| Date de publication | Date de première publication | Non |
| Couverture | Upload d'image ou URL externe | Non |
| One-shot | Indique que la série est un tome unique | Non |
| Dernier tome publié | Numéro du dernier tome sorti | Non |
| Parution terminée | Indique que la série est terminée | Non |

### Gestion des auteurs

Le champ auteur utilise l'auto-complétion :
- Commencer à taper un nom pour voir les suggestions
- Sélectionner un auteur existant ou en créer un nouveau
- Plusieurs auteurs peuvent être ajoutés

### Gestion des tomes

Pour les séries non one-shot, une section permet d'ajouter des tomes :
- Cliquer sur **Ajouter un tome** pour créer une nouvelle ligne
- Chaque tome a un numéro, un titre optionnel, un ISBN optionnel, et des cases à cocher (acheté, téléchargé, sur le NAS, lu)
- Les tomes peuvent être supprimés individuellement

---

## Corbeille

Les séries supprimées sont déplacées dans la corbeille et conservées pendant un certain temps.

### Restaurer une série

Cliquer sur **Restaurer** pour remettre la série dans la bibliothèque avec son statut d'origine.

### Suppression définitive

Cliquer sur **Supprimer définitivement** pour effacer la série de manière irréversible. Une confirmation est demandée avant la suppression.

---

## Installation PWA

L'application peut être installée comme une application native sur mobile et desktop.

### Sur mobile (Android/iOS)

1. Ouvrir l'application dans le navigateur
2. **Android** : appuyer sur le menu ⋮ → « Installer l'application » ou « Ajouter à l'écran d'accueil »
3. **iOS** : appuyer sur le bouton Partager → « Sur l'écran d'accueil »

### Sur desktop (Chrome/Edge)

1. Ouvrir l'application
2. Cliquer sur l'icône d'installation dans la barre d'adresse
3. Confirmer l'installation

### Mode hors-ligne

Une fois installée, l'application fonctionne en mode hors-ligne :
- Les pages déjà visitées sont accessibles sans connexion
- Les couvertures sont mises en cache
- Les données de l'API sont disponibles pendant 7 jours en cache
- Les modifications nécessitent une connexion internet
