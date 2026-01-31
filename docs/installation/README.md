# Guide d'installation

Ce guide vous accompagne dans l'installation de Ma Bibliotheque BD sur votre environnement local.

---

## Prérequis

### Option 1 : Installation avec DDEV (recommandée)

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- [DDEV](https://ddev.readthedocs.io/en/stable/users/install/)

### Option 2 : Installation manuelle

- PHP 8.3+
- Composer 2+
- MariaDB 10.11+ ou MySQL 8+
- Extensions PHP : `intl`, `pdo_mysql`, `gd`

---

## Installation avec DDEV

DDEV est l'outil recommandé car il fournit un environnement Docker pré-configuré.

```bash
# 1. Cloner le projet
git clone https://github.com/Soviann/bibliotheqe.git
cd bibliotheqe

# 2. Démarrer DDEV
ddev start

# 3. Installer les dépendances PHP
ddev composer install

# 4. Créer la base de données
ddev exec bin/console doctrine:database:create

# 5. Exécuter les migrations
ddev exec bin/console doctrine:migrations:migrate -n

# 6. Créer un utilisateur
ddev exec bin/console app:create-user votre@email.com motdepasse

# 7. Ouvrir l'application
ddev launch
```

L'application est accessible sur : **https://bibliotheque.ddev.site**

Pour plus de détails sur DDEV, voir le [guide dédié](ddev.md).

---

## Installation manuelle

### 1. Cloner le projet

```bash
git clone https://github.com/Soviann/bibliotheqe.git
cd bibliotheqe
```

### 2. Installer les dépendances

```bash
composer install
```

### 3. Configurer l'environnement

Copiez le fichier d'environnement et configurez-le :

```bash
cp .env .env.local
```

Modifiez `.env.local` avec vos paramètres :

```dotenv
# Base de données
DATABASE_URL="mysql://user:password@127.0.0.1:3306/bibliotheque?serverVersion=10.11.0-MariaDB"

# Clé secrète (générez-en une avec: openssl rand -hex 32)
APP_SECRET=votre_cle_secrete_ici
```

### 4. Créer la base de données

```bash
# Créer la base de données
bin/console doctrine:database:create

# Exécuter les migrations
bin/console doctrine:migrations:migrate -n
```

### 5. Créer un utilisateur

```bash
bin/console app:create-user votre@email.com motdepasse
```

### 6. Lancer le serveur de développement

Avec Symfony CLI :

```bash
symfony server:start
```

Ou avec le serveur PHP intégré :

```bash
php -S localhost:8000 -t public
```

---

## Import de données existantes

Si vous avez une collection existante dans un fichier Excel, vous pouvez l'importer :

```bash
# Avec DDEV
ddev exec bin/console app:import-excel chemin/vers/fichier.xlsx

# Sans DDEV
bin/console app:import-excel chemin/vers/fichier.xlsx
```

### Format du fichier Excel

Le fichier doit contenir des onglets nommés selon les types :
- `BD`
- `Comics`
- `Livre`
- `Mangas`

Colonnes attendues dans chaque onglet :
| Colonne | Description |
|---------|-------------|
| A | Titre de la série |
| B | Numéro actuel possédé |
| C | Dernier acheté |
| D | Nombre de parutions |
| E | Dernier téléchargé |
| F | Sur NAS (oui/non) |
| G | Numéros possédés (séparés par virgule) |
| H | Numéros manquants (séparés par virgule) |
| I | Statut (En cours, Terminé, Arrêté) |

### Mode simulation

Pour tester l'import sans modifier la base de données :

```bash
ddev exec bin/console app:import-excel fichier.xlsx --dry-run
```

---

## Vérification de l'installation

Après l'installation, vérifiez que tout fonctionne :

1. Accédez à l'application dans votre navigateur
2. Connectez-vous avec les identifiants créés
3. Ajoutez une première série pour tester

### Dépannage courant

| Problème | Solution |
|----------|----------|
| Erreur de connexion BDD | Vérifiez `DATABASE_URL` dans `.env.local` |
| Page blanche | Exécutez `bin/console cache:clear` |
| Assets non chargés | Vérifiez que le serveur web serve `/public` |
| Erreur 500 | Consultez `var/log/dev.log` pour les détails |

---

## Étape suivante

- [Configuration DDEV détaillée](ddev.md)
- [Tour des fonctionnalités](../fonctionnalites/README.md)
