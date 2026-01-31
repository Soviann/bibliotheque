# Gestion de collection

Ce guide explique comment gérer votre collection de BD, Comics, Mangas et Livres.

---

## Ajouter une série

### Depuis la page d'accueil

1. Cliquez sur le bouton **+** (en bas à droite sur mobile)
2. Choisissez le **type** de publication
3. Utilisez la **recherche ISBN** ou **recherche par titre** pour préremplir
4. Complétez les informations manquantes
5. Ajoutez les tomes que vous possédez
6. Cliquez sur **Enregistrer**

### Champs disponibles

| Champ | Obligatoire | Description |
|-------|:-----------:|-------------|
| Type | Oui | BD, Comics, Manga ou Livre |
| Titre | Oui | Nom de la série |
| Auteurs | Non | Liste des auteurs (autocomplétion) |
| Éditeur | Non | Maison d'édition |
| Date de publication | Non | Date de première parution |
| Description | Non | Synopsis ou résumé |
| Couverture | Non | Image uploadée ou URL |
| One-shot | Non | Cochez si volume unique |
| Dernier tome paru | Non | Numéro du dernier tome publié |
| Série terminée | Non | L'éditeur a terminé la publication |

---

## Gérer les tomes

### Ajouter un tome

Dans le formulaire d'édition :

1. Cliquez sur **Ajouter un tome**
2. Renseignez le numéro du tome
3. Cochez les attributs :
   - **Acheté** : vous le possédez physiquement
   - **Téléchargé** : vous avez la version numérique
   - **Sur NAS** : stocké sur votre serveur
4. Optionnel : ajoutez l'ISBN et un titre spécifique

### Supprimer un tome

Cliquez sur le bouton de suppression (icône poubelle) à côté du tome.

### Numérotation

- Les tomes sont numérotés à partir de 0 (pour les éditions spéciales) ou 1
- Le numéro 0 est accepté pour les tomes "hors-série"
- Les tomes sont affichés par ordre numérique

---

## One-shots

Un one-shot est une publication en volume unique (intégrale, roman graphique autonome...).

### Créer un one-shot

1. Cochez la case **One-shot** dans le formulaire
2. Un tome avec le numéro 1 est créé automatiquement
3. Les champs "Dernier tome paru" et "Série terminée" sont pré-remplis

### Comportement spécifique

- Le bouton "Ajouter un tome" est masqué
- Le bouton de suppression du tome est masqué
- La page de détail affiche un badge "Tome unique"
- La carte n'affiche pas les détails de tomes

---

## Recherche et filtres

### Barre de recherche

Recherchez par :
- Titre de série (correspondance partielle)
- ISBN d'un tome

### Filtres disponibles

| Filtre | Options |
|--------|---------|
| Type | Tous, BD, Comics, Manga, Livre |
| Statut | Tous, En cours, Terminé, Arrêté |
| NAS | Tous, Sur NAS uniquement |

### Options de tri

| Tri | Description |
|-----|-------------|
| Titre A-Z | Alphabétique croissant |
| Titre Z-A | Alphabétique décroissant |
| Modifié récemment | Plus récent en premier |
| Statut | Groupé par statut |

---

## Statuts de collection

### En cours d'achat

- La série est toujours publiée
- Vous achetez les nouveaux tomes régulièrement
- Indicateur de progression affiché (X/Y tomes)

### Terminé

- Vous possédez tous les tomes de la série
- La série peut être terminée ou en cours de publication

### Arrêté

- Vous avez arrêté d'acheter cette série
- Historique conservé pour référence

### Modifier le statut

1. Ouvrez la page de détail ou le formulaire d'édition
2. Modifiez le champ **Statut**
3. Enregistrez

---

## Liste de souhaits

### Ajouter à la liste de souhaits

Lors de la création d'une série :
1. Cochez la case **Liste de souhaits**
2. La série apparaîtra dans l'onglet Wishlist

Ou sélectionnez le statut **Liste de souhaits**.

### Transférer vers la bibliothèque

Quand vous achetez une série de votre wishlist :
1. Accédez à la page de détail de la série
2. Cliquez sur **Ajouter à la bibliothèque**
3. La série passe en statut "En cours d'achat"

---

## Couvertures

### Upload manuel

1. Dans le formulaire, cliquez sur la zone de dépôt
2. Glissez-déposez une image ou cliquez pour parcourir
3. Formats acceptés : JPEG, PNG, GIF, WebP
4. Taille maximale : 5 Mo

### URL externe

Si vous ne souhaitez pas uploader :
1. Collez l'URL de la couverture dans le champ prévu
2. L'image sera affichée depuis l'URL externe

### Priorité

1. Image uploadée (prioritaire)
2. URL externe (si pas d'upload)
3. Placeholder (si aucune image)

---

## Supprimer une série

1. Accédez à la page de détail
2. Cliquez sur **Supprimer**
3. Confirmez la suppression

**Attention** : La suppression est définitive et supprime tous les tomes associés.

---

## Bonnes pratiques

### Organisation

- Utilisez les statuts pour suivre l'avancement
- Marquez "Sur NAS" les tomes archivés
- Gardez les ISBNs pour retrouver facilement les tomes

### Maintenance

- Mettez à jour "Dernier tome paru" régulièrement
- Passez en "Terminé" les collections complètes
- Nettoyez les séries arrêtées depuis longtemps

---

## Étapes suivantes

- [Recherche ISBN](recherche-isbn.md) - Préremplissage automatique
- [Mode PWA](pwa.md) - Accès hors-ligne
