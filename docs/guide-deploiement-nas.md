# Déploiement NAS Synology (Docker Compose via SSH)

Guide pas-à-pas pour déployer l'application sur un NAS Synology DS920+ via Docker Compose en SSH.

> **Note** : Container Manager (l'interface graphique DSM) ne supporte pas la syntaxe du fichier docker-compose utilisé (Docker Engine trop ancien). Le déploiement se fait en ligne de commande via SSH.

---

## Prérequis

- Synology DSM 7.2+
- Docker (Container Manager) installé depuis le Centre de paquets
- Accès SSH activé (DSM > Panneau de configuration > Terminal & SNMP > Activer SSH)
- Git installé (voir ci-dessous) ou accès à une machine avec Git pour cloner et transférer via `scp`/`rsync`
- Le dépôt contient les clés de chiffrement du vault Symfony (fichiers `config/secrets/prod/`)
- Un nom de domaine pointant vers l'IP publique du NAS (ou DDNS Synology)
- Ports 80 et 443 redirigés (NAT/port forwarding) depuis le routeur vers le NAS

### Installer le client Git sur le NAS (optionnel)

> **Attention** : le paquet **Git Server** (Centre de paquets Synology) sert à héberger des dépôts, ce n'est pas ce qu'il faut ici. On a besoin uniquement du **client git** (la commande `git`).

**Option A — SynoCommunity** : ajouter `https://packages.synocommunity.com` dans Centre de paquets > Paramètres > Sources, puis installer le paquet **Git** (pas "Git Server").

**Option B — sans Git** : cloner le dépôt sur une autre machine et transférer :

```bash
# Sur votre machine locale
git clone https://github.com/Soviann/bibliotheque.git
scp -r bibliotheque admin@nas-ip:/volume1/docker/bibliotheque/
```

### Mettre à jour Docker Compose (si nécessaire)

Le Docker Compose embarqué dans DSM peut être trop ancien. Vérifier la version :

```bash
docker compose version
```

Si la version est inférieure à v2.24, mettre à jour :

```bash
sudo cp /usr/local/bin/docker-compose /usr/local/bin/docker-compose.bak
sudo curl -L "https://github.com/docker/compose/releases/download/v2.32.4/docker-compose-linux-x86_64" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose
```

---

## 1. Cloner le dépôt

```bash
# Se connecter en SSH au NAS
ssh utilisateur@nas-ip

# Créer le dossier de travail
sudo mkdir -p /volume1/docker/bibliotheque
sudo chown $(whoami):users /volume1/docker/bibliotheque
cd /volume1/docker/bibliotheque

# Cloner le dépôt (SSH — nécessite une clé SSH configurée sur GitHub)
git clone git@github.com:Soviann/bibliotheque.git
cd bibliotheque
```

---

## 2. Configurer les variables d'environnement

Docker Compose charge automatiquement le fichier `backend/.env` pour l'interpolation des variables. Créer ce fichier avec les vraies valeurs :

```bash
cat > backend/.env << 'EOF'
# Port exposé par nginx
APP_PORT=8082

# Base de données (pas de caractères spéciaux : / @ # + %)
MYSQL_PASSWORD=mot_de_passe_securise
MYSQL_ROOT_PASSWORD=mot_de_passe_root_securise

# Symfony vault (secrets chiffrés)
SYMFONY_DECRYPTION_SECRET=valeur_de_la_cle

# Google OAuth
OAUTH_GOOGLE_ID=votre_google_client_id.apps.googleusercontent.com
OAUTH_ALLOWED_EMAIL=votre_email@gmail.com

# CORS et URL de production (adapter le domaine)
CORS_ALLOW_ORIGIN='^https://bibliotheque\.votre-domaine\.fr$'
DEFAULT_URI=https://bibliotheque.votre-domaine.fr

# Clés API (optionnel — lookup ISBN/titre et recherche de couvertures)
GEMINI_API_KEYS=cle1,cle2,cle3
GOOGLE_BOOKS_API_KEY=votre_cle_google_books
SERPER_API_KEY=votre_cle_serper
EOF
```

> **Important** : les mots de passe MySQL ne doivent pas contenir de caractères spéciaux (`/`, `@`, `#`, `+`, `%`) car ils sont interpolés dans l'URL de connexion. Utiliser `openssl rand -hex 24` pour générer des mots de passe sûrs.

### Obtenir OAUTH_GOOGLE_ID

Dans la [Google Cloud Console](https://console.cloud.google.com/) :
1. Créer un projet + configurer l'**écran de consentement OAuth** (type Externe, nom de l'app, email de support)
2. Ajouter l'email autorisé dans **Utilisateurs de test** (obligatoire tant que l'app est en mode "Test")
3. Créer des identifiants **ID client OAuth 2.0** (type **Application Web**)
4. Ajouter dans **Origines JavaScript autorisées** : l'URL de prod (ex. `https://bibliotheque.votre-domaine.fr`) et l'URL de dev si besoin (`https://bibliotheque.ddev.site:5173`)
5. Copier l'**ID client** dans `OAUTH_GOOGLE_ID`
6. Mettre l'email Gmail autorisé dans `OAUTH_ALLOWED_EMAIL`

> **Note** : un seul ID client suffit pour dev et prod, il faut juste que les origines JS contiennent les deux URLs. Pour supprimer l'écran d'avertissement "App non validée", publier l'app (pas de vérification Google requise pour les scopes `email`/`profile`).

### Obtenir SERPER_API_KEY (recherche de couvertures)

Créer un compte sur [serper.dev](https://serper.dev/), copier la clé API dans `SERPER_API_KEY`. Le plan gratuit offre 2 500 requêtes. Optionnel : sans cette clé, la recherche de couvertures se limite à Google Books.

### Obtenir SYMFONY_DECRYPTION_SECRET

Sur la **machine de développement** (pas le NAS) :

```bash
ddev exec "cd backend && php -r 'echo base64_encode(include \"config/secrets/prod/prod.decrypt.private.php\");'"
```

Copier la sortie dans `SYMFONY_DECRYPTION_SECRET` du fichier `.env` ci-dessus.

---

## 3. Construire et démarrer

```bash
cd /volume1/docker/bibliotheque/bibliotheque/backend
sudo docker compose -f docker-compose.prod.yml up --build -d
```

Vérifier que les 3 conteneurs tournent :

```bash
sudo docker compose -f docker-compose.prod.yml ps
```

Les 3 conteneurs (`nginx`, `php`, `db`) doivent être `Up`. Le conteneur `db` doit afficher `healthy` (environ 30 secondes).

---

## 4. Initialiser l'application

```bash
cd /volume1/docker/bibliotheque/bibliotheque/backend

# Générer les clés JWT
sudo docker compose -f docker-compose.prod.yml exec php php bin/console lexik:jwt:generate-keypair --env=prod

# Exécuter les migrations
sudo docker compose -f docker-compose.prod.yml exec php php bin/console doctrine:migrations:migrate -n --env=prod
```

> Pas de création d'utilisateur manuelle : le premier login Google crée le compte automatiquement.

---

## 5. Vérifier le fonctionnement

Depuis un navigateur sur le réseau local, accéder à `http://nas-ip:8082`. La page de login doit s'afficher.

**Vérification API** (optionnel, via SSH) :

```bash
# La page d'accueil répond
curl -s -o /dev/null -w "%{http_code}" http://localhost:8082
# Attendu : 200

# L'endpoint Google login répond
curl -s -X POST http://localhost:8082/api/login/google \
  -H "Content-Type: application/json" \
  -d '{}' \
  | head -c 100
# Attendu : {"error":"Paramètre \"credential\" manquant."}
```

---

## 6. Configurer le réseau et le reverse proxy (HTTPS)

### DNS et port forwarding

1. **DNS** : créer un enregistrement A pointant `bibliotheque.votre-domaine.fr` vers l'IP publique du NAS, ou activer le **DDNS Synology** (DSM > Panneau de configuration > Accès externe > DDNS)
2. **Port forwarding** : sur le routeur, rediriger les ports **80** et **443** (TCP) vers l'IP locale du NAS. Le port 80 est nécessaire pour le challenge Let's Encrypt.

### Reverse proxy

Dans DSM > **Panneau de configuration > Portail de connexion > Avancé > Proxy inversé** :

1. Cliquer **Créer**
2. Remplir :

| Paramètre | Valeur |
|-----------|--------|
| Nom | Bibliotheque |
| Source protocole | HTTPS |
| Source nom d'hôte | bibliotheque.votre-domaine.fr |
| Source port | 443 |
| Destination protocole | HTTP |
| Destination nom d'hôte | localhost |
| Destination port | 8082 |

### Certificat HTTPS (Let's Encrypt)

1. DSM > **Panneau de configuration > Sécurité > Certificat** > **Ajouter**
2. Choisir **Ajouter un nouveau certificat** > **Obtenir un certificat de Let's Encrypt**
3. Remplir le nom de domaine (`bibliotheque.votre-domaine.fr`) et l'email
4. Dans l'onglet **Paramètres**, associer ce certificat au service **Bibliotheque** (le reverse proxy créé ci-dessus)

L'application est maintenant accessible sur `https://bibliotheque.votre-domaine.fr`.

---

## 7. Tâches planifiées

Dans DSM > **Panneau de configuration > Planificateur de tâches** :

### Purger les séries supprimées (quotidien, 3h)

- Type : Script défini par l'utilisateur
- Planification : tous les jours à 03:00
- Commande :

```bash
cd /volume1/docker/bibliotheque/bibliotheque/backend && sudo docker compose -f docker-compose.prod.yml exec -T php php bin/console app:purge-deleted --env=prod
```

---

## 8. Mise à jour de l'application

```bash
cd /volume1/docker/bibliotheque/bibliotheque && git pull
cd backend && sudo docker compose -f docker-compose.prod.yml up --build -d

# Migrations si nécessaire
sudo docker compose -f docker-compose.prod.yml exec php php bin/console doctrine:migrations:migrate -n --env=prod
```

---

## 9. Sauvegardes

### Hyper Backup (recommandé)

Configurer Hyper Backup pour sauvegarder :
- `/volume1/docker/bibliotheque/` (code source + .env)

### Sauvegarde manuelle de la base de données

```bash
cd /volume1/docker/bibliotheque/bibliotheque/backend
export MYSQL_PASSWORD=$(grep '^MYSQL_PASSWORD=' .env | sed 's/^MYSQL_PASSWORD=//')
sudo docker compose -f docker-compose.prod.yml exec -T db mysqldump -u biblio -p"${MYSQL_PASSWORD}" bibliotheque | gzip > /volume1/docker/bibliotheque/backup_$(date +%Y%m%d).sql.gz
```

### Restauration

```bash
cd /volume1/docker/bibliotheque/bibliotheque/backend
export MYSQL_PASSWORD=$(grep '^MYSQL_PASSWORD=' .env | sed 's/^MYSQL_PASSWORD=//')
gunzip -c /volume1/docker/bibliotheque/backup_YYYYMMDD.sql.gz | sudo docker compose -f docker-compose.prod.yml exec -T db mysql -u biblio -p"${MYSQL_PASSWORD}" bibliotheque
```

---

## 10. Dépannage

### Voir les logs

```bash
cd /volume1/docker/bibliotheque/bibliotheque/backend

# Logs récents d'un service
sudo docker compose -f docker-compose.prod.yml logs php --tail=50
sudo docker compose -f docker-compose.prod.yml logs nginx --tail=50
sudo docker compose -f docker-compose.prod.yml logs db --tail=50
```

### Redémarrer les conteneurs

```bash
sudo docker compose -f docker-compose.prod.yml restart
```

### Recréer complètement (sans perdre les données)

```bash
sudo docker compose -f docker-compose.prod.yml down
sudo docker compose -f docker-compose.prod.yml up --build -d
```

### Réinitialiser la base de données (DESTRUCTIF)

```bash
sudo docker compose -f docker-compose.prod.yml down -v  # Supprime les volumes
sudo docker compose -f docker-compose.prod.yml up --build -d
# Puis refaire l'étape 4 (initialisation)
```

---

## Résumé de l'architecture Docker

| Conteneur | Image | Port | Rôle |
|-----------|-------|------|------|
| nginx | nginx:alpine + frontend build | 80 → `${APP_PORT:-8080}` | Sert le SPA React, proxy `/api` vers php-fpm, sert `/uploads` et `/media` |
| php | php:8.3-fpm + Symfony | 9000 (interne) | PHP-FPM, traite les requêtes API |
| db | mariadb:10.11 | 3306 (interne) | Base de données |

### Volumes Docker

| Volume | Monté dans | Usage |
|--------|-----------|-------|
| `uploads` | nginx (ro) + php (rw) | Couvertures uploadées |
| `media` | nginx (ro) + php (rw) | Thumbnails LiipImagine |
| `jwt_keys` | php | Clés JWT (persiste entre rebuilds) |
| `app_var` | php | Cache et logs Symfony |
| `db_data` | db | Données MariaDB |
