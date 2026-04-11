# Guide utilisateur

## Accès et connexion

L'application est une **Progressive Web App** (PWA) accessible depuis un navigateur web. Seuls les utilisateurs disposant d'un compte peuvent y accéder.

### Se connecter

1. Ouvrir l'URL de l'application dans un navigateur
2. Cliquer sur **Se connecter avec Google**
3. S'authentifier avec le compte Google autorisé

La session reste active pendant **365 jours** grâce au token JWT, ce qui permet une utilisation hors-ligne de la PWA.

### Se déconnecter

Cliquer sur **Déconnexion** dans la barre de navigation (header desktop ou menu mobile).

---

## Navigation

L'interface s'adapte à la taille de l'écran :

- **Mobile** : barre de navigation en bas de l'écran avec 4 onglets (glassmorphism en dark mode, indicateurs dot)
- **Desktop** : header sticky avec backdrop-blur en haut

| Onglet | Description |
|--------|-------------|
| Accueil | Bibliothèque complète |
| À acheter | Tomes manquants des séries en cours |
| Ajouter | Formulaire de création |
| Corbeille | Séries supprimées |

Le header contient également :
- **Recherche** : icône loupe qui ouvre un champ pleine largeur (Enter recherche, Escape ferme)
- **Notifications** : cloche avec badge de compteur non lu
- **Outils** : accès à la section d'administration
- **Mode sombre/clair** : bouton de bascule

---

## Bibliothèque (page d'accueil)

La page d'accueil affiche toutes les séries de la collection sous forme de grille de cartes.

### Carrousel « Récemment ajoutés »

En haut de page, un carrousel horizontal présente les dernières séries ajoutées à la collection.

### Cartes de série

Chaque carte affiche :
- La couverture (avec halo coloré en dark mode — « ambient glow »)
- Le titre
- Le(s) auteur(s)
- Le type (BD, Manga, Comics, Livre) en badge
- Le nombre de tomes
- Le statut

Cliquer sur une carte ouvre la page de détail de la série.

### Filtres

Des **chips de filtre rapide** (type et statut) sont affichés au-dessus de la grille, scrollables horizontalement sur mobile. Cliquer un chip active le filtre ; cliquer à nouveau le désactive.

En complément, deux menus déroulants permettent de filtrer :

- **Type** : BD, Manga, Comics, Livre
- **Statut** : En cours d'achat, Terminé, Arrêté, Liste de souhaits

### Tri

Un sélecteur de tri permet d'ordonner les séries :

- **Titre A→Z / Z→A** : Tri alphabétique (par défaut A→Z)
- **Plus récent / Plus ancien** : Par date d'ajout
- **Plus de tomes / Moins de tomes** : Par nombre de tomes

### Pull-to-refresh

Sur mobile, un geste tactile (tirer vers le bas) rafraîchit les données avec un indicateur visuel.

---

## À acheter

La page **À acheter** (`/to-buy`) liste les tomes manquants des séries en cours d'achat. Les tomes manquants sont affichés sous forme de tranches (ex : « T.1-3, T.5 »). Un carrousel « Récemment ajoutés » apparaît en haut de page, et les mêmes filtres (chips, type, tri) sont disponibles.

---

## Recherche

La recherche est accessible depuis le champ en haut de la page d'accueil. Elle filtre les séries par titre, auteur ou éditeur, avec un délai anti-rebond de 300 ms après saisie.

---

## Détail d'une série

La page de détail affiche toutes les informations d'une série :

- Couverture en grand format (cliquer pour un **zoom plein écran** via lightbox)
- Titre, auteur(s), éditeur
- Type et statut
- Description (section dédiée)
- Date de publication, nombre de tomes publiés
- Lien Amazon (si renseigné)
- Métadonnées en grille clé-valeur
- Bannière d'alerte si des tomes parus ne sont pas encore ajoutés

### Suivi d'auteurs

Un bouton **follow/unfollow** est disponible pour chaque auteur affiché sur la fiche. Suivre un auteur permet de recevoir des notifications quand il publie une nouvelle série.

### Gestion des tomes

Si la série n'est pas un one-shot, la liste des tomes s'affiche :
- **Desktop** : tableau avec colonnes triables (#, Titre, Acheté, Téléchargé, Lu, NAS)
- **Mobile (< 768px)** : cartes dépliables — vue repliée `#N - Titre` avec checkbox « Acheté » en accès rapide, déplier pour éditer les autres champs
- Cases à cocher interactives — un clic bascule le statut directement (mise à jour optimiste, support hors-ligne)
- **Actions en masse** : checkbox dans les en-têtes de colonne pour cocher/décocher tous les tomes (état indeterminate)

### Historique d'enrichissement

Un panneau dépliable affiche l'historique des enrichissements automatiques de la série (auto-appliqué, accepté, rejeté, ignoré), avec pour chaque entrée : date, action, champ modifié, niveau de confiance, source, et valeurs avant/après.

### Actions disponibles

- **Modifier** : ouvre le formulaire d'édition
- **Amazon** : ouvre le lien Amazon (si renseigné)
- **Supprimer** : déplace la série dans la corbeille (suppression douce)

---

## Ajouter / modifier une série

Le formulaire de création/édition est organisé en **sections repliables** (Info générale, Publication, Média).

### Recherche automatique (Lookup)

Avant de remplir manuellement, vous pouvez utiliser la recherche automatique :

1. **Par ISBN** : saisir un ISBN-10 ou ISBN-13, l'application interroge plusieurs APIs (Google Books, BnF, OpenLibrary, AniList, Wikipedia, Gemini, Bedetheque) pour pré-remplir les champs
2. **Par titre** : saisir un titre et l'application recherche les informations correspondantes
3. **Par scan de code-barres** : utiliser la caméra du téléphone pour scanner le code-barres d'un livre/manga

Les champs pré-remplis peuvent être modifiés avant la sauvegarde. Le lookup fonctionne même quand les sections sont repliées.

### Recherche de couvertures

Une modale dédiée permet de rechercher des couvertures via Google Books et Serper (images web). Les résultats s'affichent en grille avec un indicateur de scroll.

### Champs du formulaire

| Champ | Description | Obligatoire |
|-------|-------------|:-----------:|
| Titre | Nom de la série | Oui |
| Type | BD, Manga, Comics, Livre | Oui |
| Statut | En cours d'achat, Terminé, Arrêté, Liste de souhaits | Oui |
| Auteur(s) | Un ou plusieurs auteurs (auto-complétion) | Non |
| Éditeur | Maison d'édition | Non |
| Description | Résumé ou notes | Non |
| Date de publication | Date de première publication | Non |
| Couverture | Upload d'image, URL externe ou recherche | Non |
| URL Amazon | Lien vers la page Amazon de la série | Non |
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
- Cliquer sur **Ajouter** pour créer une nouvelle ligne
- **Ajout en lot** : Renseigner les champs « Du tome X » et « au tome Y » puis cliquer sur **Générer** pour créer plusieurs tomes d'un coup. Les numéros déjà existants sont ignorés
- Chaque tome a un numéro, un numéro de fin optionnel (pour les intégrales multi-numéros, ex : tome 4-6), un titre optionnel, un ISBN optionnel, et des cases à cocher (acheté, téléchargé, sur le NAS, lu)
- Les tomes peuvent être supprimés individuellement

---

## Notifications

### Cloche de notifications

Une icône cloche dans le header affiche un badge avec le nombre de notifications non lues.

### Page de notifications (`/notifications`)

La page liste toutes les notifications avec possibilité de :
- Marquer comme lu
- Supprimer
- Marquer toutes comme lues

### Types de notifications

- **Tomes manquants** : détection automatique de tomes non ajoutés pour les séries en cours
- **Nouvelles publications** : alertes pour les séries suivies quand de nouveaux tomes sortent
- **Publications d'auteurs suivis** : alerte quand un auteur suivi publie une nouvelle série

### Préférences (`/settings/notifications`)

Chaque type de notification peut être configuré individuellement :
- **In-app** : notification visible dans la cloche
- **Push** : notification push du navigateur
- **Les deux**
- **Désactivé**

### Notifications push

Les notifications push nécessitent l'autorisation du navigateur. Elles fonctionnent même quand l'application est fermée.

---

## Corbeille

Les séries supprimées sont déplacées dans la corbeille et conservées pendant un certain temps.

### Restaurer une série

Cliquer sur **Restaurer** pour remettre la série dans la bibliothèque avec son statut d'origine.

### Suppression définitive

Cliquer sur **Supprimer définitivement** pour effacer la série de manière irréversible. Une confirmation est demandée avant la suppression.

---

## Outils d'administration

Accessible via l'icône **clé à molette** dans le header ou la route `/tools`, cette section regroupe les outils d'administration. Un fil d'Ariane « Outils / Nom de la page » facilite la navigation.

### Lookup batch

Lancer une recherche automatique de métadonnées sur toutes les séries incomplètes. Filtres disponibles : type, forcer le re-lookup, limite, délai entre les requêtes. Le progrès s'affiche en temps réel via streaming SSE.

### Revue d'enrichissement (`/tools/enrichment-review`)

Valider ou rejeter les propositions d'enrichissement automatique. Filtres disponibles : recherche par série, filtre par champ, niveau de confiance et source. Chaque proposition affiche les valeurs avant/après et peut être acceptée ou rejetée.

### Suggestions IA (`/tools/suggestions`)

Suggestions de séries similaires générées par Gemini AI à partir de la collection existante. Chaque suggestion peut être ajoutée à la bibliothèque ou ignorée.

### Import Excel

Importer des données depuis des fichiers Excel :
- **Onglet Suivi** : fichier de suivi avec feuilles BD/Comics/Livre/Mangas
- **Onglet Livres** : fichier Livres.xlsx avec ISBN, auteurs, éditeur, couverture

Chaque import peut être lancé en mode **simulation** (dry run) pour vérifier sans persister.

### Fusion de séries

Détecter automatiquement (via Gemini AI) les séries potentiellement duplicates, ou sélectionner manuellement des séries à fusionner. Une étape de confirmation permet de vérifier et exclure des séries avant la prévisualisation des tomes. Aperçu complet et éditable avant exécution.

### Purge

Supprimer définitivement les séries dans la corbeille depuis plus de N jours. Prévisualisation de la liste avant confirmation.

---

## Mode sombre

Un bouton dans le header permet de basculer entre le mode clair et le mode sombre. La préférence est sauvegardée dans le navigateur.

- **Mode clair** : design « Refined Collector » — typographie serif (Playfair Display), palette warm off-white et cognac
- **Mode sombre** : design « Dark Luxe » — typographie sans-serif (DM Sans), palette deep navy et indigo, glassmorphism, ambient glow sur les couvertures

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
- Les modifications (ajout, édition, suppression de séries et tomes) sont enregistrées localement et synchronisées automatiquement au retour en ligne
- Une bannière indique le nombre d'opérations en attente de synchronisation (dépliable pour voir le détail)
- La recherche automatique (ISBN/titre) et le scanner sont indisponibles hors-ligne
