# Guide de déploiement

Ce guide décrit le déploiement de l'application en production :
- [Serveur Linux OVH](#serveur-linux-ovh) (dédié ou VPS, sans DDEV)
- [NAS Synology DS920+](#nas-synology-ds920) (Docker Compose)
- [Gestion des secrets Symfony](#gestion-des-secrets-symfony) (vault chiffré)

---

# Serveur Linux OVH

Déploiement sur un serveur Linux dédié ou VPS OVH, **sans DDEV**.

## Prérequis serveur

| Composant | Version minimum | Installation |
|-----------|----------------|-------------|
| OS | Debian 12 / Ubuntu 22.04+ | — |
| PHP | 8.3 | PPA `ondrej/php` |
| MariaDB | 10.11 | Dépôt MariaDB officiel |
| Composer | 2 | getcomposer.org |
| Node.js | 20 LTS | NodeSource |
| nginx | 1.24+ | Dépôt système |
| certbot | — | Dépôt système |
| git | — | Dépôt système |

---

## 1. Installation des dépendances système

```bash
# Mettre à jour le système
sudo apt update && sudo apt upgrade -y

# PHP 8.3 + extensions requises
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y   # Ubuntu
# Debian : wget https://packages.sury.org/php/README.txt et suivre les instructions
sudo apt update
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-xml php8.3-mbstring \
    php8.3-intl php8.3-curl php8.3-gd php8.3-zip php8.3-opcache

# MariaDB
sudo apt install -y mariadb-server

# nginx
sudo apt install -y nginx

# Node.js 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Certbot (SSL)
sudo apt install -y certbot python3-certbot-nginx

# Git
sudo apt install -y git
```

---

## 2. Créer un utilisateur dédié

```bash
sudo adduser --disabled-password --gecos "" bibliotheque
sudo usermod -aG www-data bibliotheque
```

---

## 3. Configurer MariaDB

```bash
sudo mysql_secure_installation

# Créer la base et l'utilisateur
sudo mysql -e "
  CREATE DATABASE bibliotheque CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'bibliotheque'@'localhost' IDENTIFIED BY 'MOT_DE_PASSE_SECURISE';
  GRANT ALL PRIVILEGES ON bibliotheque.* TO 'bibliotheque'@'localhost';
  FLUSH PRIVILEGES;
"
```

---

## 4. Cloner et configurer le projet

```bash
sudo -u bibliotheque -i

# Cloner le dépôt
git clone https://github.com/Soviann/bibliotheque.git ~/app
cd ~/app
```

### Configurer le backend

```bash
cd ~/app/backend

# Installer les dépendances PHP (sans les dépendances de développement)
composer install --no-dev --optimize-autoloader

# Créer le fichier d'environnement local
cp .env .env.local
```

Modifier `~/app/backend/.env.local` :

```env
APP_ENV=prod
DATABASE_URL="mysql://bibliotheque:MOT_DE_PASSE_SECURISE@127.0.0.1:3306/bibliotheque?serverVersion=10.11.0-MariaDB&charset=utf8mb4"
CORS_ALLOW_ORIGIN='^https://votre-domaine\.fr$'
GEMINI_API_KEY=votre_cle_api_gemini_optionnelle
```

> **Note** : `APP_SECRET` et `JWT_PASSPHRASE` sont gérés par le vault Symfony Secrets (voir [Gestion des secrets](#gestion-des-secrets-symfony)). Il n'est plus nécessaire de les définir dans `.env.local`.

```bash
# Copier la clé de déchiffrement du vault Symfony Secrets depuis la machine de dev
# Méthode fichier (recommandée) :
scp config/secrets/prod/prod.decrypt.private.php bibliotheque@serveur:~/app/backend/config/secrets/prod/
# OU méthode env var dans .env.local :
# SYMFONY_DECRYPTION_SECRET=valeur (voir section "Gestion des secrets Symfony")

# Compiler les fichiers d'environnement pour la production
composer dump-env prod

# Générer les clés JWT
php bin/console lexik:jwt:generate-keypair

# Exécuter les migrations
php bin/console doctrine:migrations:migrate -n

# Vider le cache
php bin/console cache:clear --env=prod

# Créer le premier utilisateur
php bin/console app:create-user admin@votre-domaine.fr motdepasse

# Créer le répertoire d'uploads
mkdir -p public/uploads/covers
chown -R bibliotheque:www-data public/uploads
chmod -R 775 public/uploads

# Corriger les permissions du cache et des logs
chown -R bibliotheque:www-data var/
chmod -R 775 var/
```

### Construire le frontend

```bash
cd ~/app/frontend

# Installer les dépendances et construire
npm ci
npm run build
```

Le build produit les fichiers statiques dans `~/app/frontend/dist/`.

---

## 5. Configurer PHP-FPM

Créer/modifier `/etc/php/8.3/fpm/pool.d/bibliotheque.conf` :

```ini
[bibliotheque]
user = bibliotheque
group = www-data
listen = /run/php/php8.3-fpm-bibliotheque.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 8
pm.max_requests = 500

; Logs
access.log = /var/log/php-fpm/bibliotheque-access.log
slowlog = /var/log/php-fpm/bibliotheque-slow.log
request_slowlog_timeout = 5s

; PHP settings
php_admin_value[error_log] = /var/log/php-fpm/bibliotheque-error.log
php_admin_flag[log_errors] = on
php_value[upload_max_filesize] = 10M
php_value[post_max_size] = 12M
php_value[memory_limit] = 256M
php_value[max_execution_time] = 30

; Opcache (pool-level)
php_admin_value[opcache.enable] = 1
php_admin_value[opcache.memory_consumption] = 128
php_admin_value[opcache.max_accelerated_files] = 10000
```

### Configurer OPcache (niveau système)

Modifier `/etc/php/8.3/fpm/conf.d/10-opcache.ini` et s'assurer que ces valeurs sont présentes :

```ini
opcache.validate_timestamps=1
opcache.revalidate_freq=2
```

- `validate_timestamps=1` : PHP vérifie la date de modification des fichiers
- `revalidate_freq=2` : l'intervalle de vérification est de 2 secondes

Après un déploiement, les fichiers modifiés sont rechargés automatiquement sous 2 secondes, sans avoir à vider le cache OPcache manuellement.

```bash
sudo mkdir -p /var/log/php-fpm
sudo systemctl restart php8.3-fpm
```

---

## 6. Configurer nginx

Créer `/etc/nginx/sites-available/bibliotheque` :

```nginx
server {
    listen 80;
    server_name votre-domaine.fr;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name votre-domaine.fr;

    # SSL sera configuré par certbot (section ajoutée automatiquement)
    # ssl_certificate ...
    # ssl_certificate_key ...

    # Racine = build React
    root /home/bibliotheque/app/frontend/dist;
    index index.html;

    # Taille max des uploads
    client_max_body_size 12M;

    # Gzip
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml image/svg+xml;
    gzip_min_length 1000;

    # Assets statiques du frontend (cache long, hash dans le nom)
    location /assets/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # Icônes, manifest PWA, service worker
    location ~* \.(png|ico|webmanifest)$ {
        expires 7d;
        add_header Cache-Control "public";
        try_files $uri =404;
    }

    # ──────────────────────────────────────────────
    # API : /api/* → PHP-FPM (Symfony)
    # ──────────────────────────────────────────────
    location ~ ^/api(/|$) {
        fastcgi_pass unix:/run/php/php8.3-fpm-bibliotheque.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /home/bibliotheque/app/backend/public/index.php;
        fastcgi_param DOCUMENT_ROOT /home/bibliotheque/app/backend/public;
        fastcgi_read_timeout 30s;
    }

    # Uploads (couvertures servies directement par nginx)
    location /uploads/ {
        alias /home/bibliotheque/app/backend/public/uploads/;
        expires 30d;
        add_header Cache-Control "public";
    }

    # Thumbnails LiipImagine
    location /media/ {
        alias /home/bibliotheque/app/backend/public/media/;
        expires 30d;
        add_header Cache-Control "public";
    }

    # SPA fallback — toute autre URL → index.html (React Router gère le routing)
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Sécurité
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "DENY" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Logs
    access_log /var/log/nginx/bibliotheque-access.log;
    error_log /var/log/nginx/bibliotheque-error.log;
}
```

```bash
# Activer le site
sudo ln -s /etc/nginx/sites-available/bibliotheque /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default

# Vérifier la config
sudo nginx -t

# Redémarrer nginx
sudo systemctl restart nginx
```

---

## 7. Obtenir un certificat SSL (Let's Encrypt)

```bash
sudo certbot --nginx -d votre-domaine.fr
```

Certbot modifie automatiquement la configuration nginx pour ajouter les directives SSL et configure le renouvellement automatique.

Vérifier le renouvellement automatique :

```bash
sudo certbot renew --dry-run
```

---

## 8. Tâches planifiées (cron)

```bash
sudo -u bibliotheque crontab -e
```

Ajouter :

```cron
# Purger les séries supprimées depuis plus de 30 jours (tous les jours à 3h)
0 3 * * * cd /home/bibliotheque/app/backend && php bin/console app:purge-deleted --env=prod

# Renouvellement SSL (certbot installe déjà un timer systemd, mais par sécurité)
0 0 1 * * /usr/bin/certbot renew --quiet
```

---

## 9. Procédure de mise à jour

Créer un script `~/app/deploy.sh` :

```bash
#!/bin/bash
set -e

APP_DIR="/home/bibliotheque/app"

echo "=== Pulling latest code ==="
cd "$APP_DIR"
git pull origin main

echo "=== Backend: installing dependencies ==="
cd "$APP_DIR/backend"
composer install --no-dev --optimize-autoloader
composer dump-env prod

echo "=== Backend: running migrations ==="
php bin/console doctrine:migrations:migrate -n --env=prod

echo "=== Backend: clearing cache ==="
php bin/console cache:clear --env=prod

echo "=== Frontend: building ==="
cd "$APP_DIR/frontend"
npm ci
npm run build

echo "=== Restarting PHP-FPM ==="
sudo systemctl restart php8.3-fpm

echo "=== Deployment complete ==="
```

```bash
chmod +x ~/app/deploy.sh
```

Pour permettre le restart PHP-FPM sans mot de passe sudo :

```bash
# /etc/sudoers.d/bibliotheque
bibliotheque ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart php8.3-fpm
```

Usage :

```bash
sudo -u bibliotheque ~/app/deploy.sh
```

---

## 10. Monitoring et logs

### Logs applicatifs

```bash
# Logs Symfony
tail -f /home/bibliotheque/app/backend/var/log/prod.log

# Logs nginx
tail -f /var/log/nginx/bibliotheque-access.log
tail -f /var/log/nginx/bibliotheque-error.log

# Logs PHP-FPM
tail -f /var/log/php-fpm/bibliotheque-error.log
tail -f /var/log/php-fpm/bibliotheque-slow.log
```

### Vérifier les services

```bash
sudo systemctl status php8.3-fpm
sudo systemctl status nginx
sudo systemctl status mariadb
```

---

## 11. Sécurisation du serveur

### Firewall (UFW)

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

### Fail2ban (protection contre le bruteforce)

```bash
sudo apt install -y fail2ban
sudo systemctl enable fail2ban
```

### Sauvegardes

Script de sauvegarde `~/backup.sh` :

```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/home/bibliotheque/backups"
mkdir -p "$BACKUP_DIR"

# Base de données
mysqldump -u bibliotheque -pMOT_DE_PASSE_SECURISE bibliotheque | gzip > "$BACKUP_DIR/db_$DATE.sql.gz"

# Uploads (couvertures)
tar czf "$BACKUP_DIR/uploads_$DATE.tar.gz" -C /home/bibliotheque/app/backend/public uploads/

# Conserver les 30 dernières sauvegardes
ls -t "$BACKUP_DIR"/db_*.sql.gz | tail -n +31 | xargs rm -f 2>/dev/null
ls -t "$BACKUP_DIR"/uploads_*.tar.gz | tail -n +31 | xargs rm -f 2>/dev/null

echo "Backup completed: $DATE"
```

```bash
chmod +x ~/backup.sh

# Sauvegarde quotidienne à 2h
(sudo -u bibliotheque crontab -l; echo "0 2 * * * /home/bibliotheque/backup.sh") | sudo -u bibliotheque crontab -
```

---

## Résumé des chemins

| Élément | Chemin |
|---------|--------|
| Code source | `/home/bibliotheque/app/` |
| Backend Symfony | `/home/bibliotheque/app/backend/` |
| Frontend build | `/home/bibliotheque/app/frontend/dist/` |
| Uploads | `/home/bibliotheque/app/backend/public/uploads/` |
| Clés JWT | `/home/bibliotheque/app/backend/config/jwt/` |
| Socket PHP-FPM | `/run/php/php8.3-fpm-bibliotheque.sock` |
| Config nginx | `/etc/nginx/sites-available/bibliotheque` |
| Config PHP-FPM | `/etc/php/8.3/fpm/pool.d/bibliotheque.conf` |
| Logs Symfony | `/home/bibliotheque/app/backend/var/log/` |
| Logs nginx | `/var/log/nginx/bibliotheque-*.log` |
| Logs PHP-FPM | `/var/log/php-fpm/bibliotheque-*.log` |
| Sauvegardes | `/home/bibliotheque/backups/` |

---

## Résumé des ports

| Service | Port | Accès |
|---------|------|-------|
| nginx HTTP | 80 | Redirige vers HTTPS |
| nginx HTTPS | 443 | Application |
| MariaDB | 3306 | Local uniquement |
| PHP-FPM | socket Unix | Local uniquement |

---

# NAS Synology DS920+

Déploiement via Docker Compose sur un NAS Synology DS920+ avec Docker installé.

## Prérequis

- Synology DS920+ avec DSM 7.2+
- Docker (Container Manager) installé depuis le Centre de paquets
- Accès SSH activé
- Un dossier partagé pour les données (ex: `/volume1/docker/bibliotheque/`)

## 1. Préparer la structure de fichiers

Depuis SSH sur le NAS :

```bash
mkdir -p /volume1/docker/bibliotheque
cd /volume1/docker/bibliotheque

# Cloner le dépôt
git clone https://github.com/Soviann/bibliotheque.git app
cd app
```

## 2. Configurer les variables d'environnement

### Créer le fichier `.env.local`

Créer `backend/.env.local` (gitignored) avec les vraies valeurs :

```env
MYSQL_PASSWORD=mot_de_passe_securise
MYSQL_ROOT_PASSWORD=mot_de_passe_root_securise
SYMFONY_DECRYPTION_SECRET=valeur_de_la_cle_de_dechiffrement
GEMINI_API_KEY=votre_cle_gemini
GOOGLE_BOOKS_API_KEY=votre_cle_google_books
```

Pour obtenir la valeur de `SYMFONY_DECRYPTION_SECRET`, exécuter sur la machine de dev :

```bash
ddev exec "cd backend && php -r 'echo base64_encode(include \"config/secrets/prod/prod.decrypt.private.php\");'"
```

> `docker-compose.prod.yml` lit `.env.prod` (placeholders committés) puis `.env.local` (override, gitignored). Les variables de `.env.local` remplacent celles de `.env.prod`.

## 3. Déployer avec Docker Compose

```bash
cd /volume1/docker/bibliotheque/app/backend
docker compose -f docker-compose.prod.yml up -d
```

## 4. Initialiser la base de données

```bash
# Exécuter les migrations
docker compose -f docker-compose.prod.yml exec app php bin/console doctrine:migrations:migrate -n --env=prod

# Créer le premier utilisateur
docker compose -f docker-compose.prod.yml exec app php bin/console app:create-user admin@votre-domaine.fr motdepasse --env=prod

# Générer les clés JWT
docker compose -f docker-compose.prod.yml exec app php bin/console lexik:jwt:generate-keypair --env=prod
```

## 5. Configurer le reverse proxy Synology

Dans DSM > **Panneau de configuration > Portail de connexion > Avancé > Proxy inversé** :

| Paramètre | Valeur |
|-----------|--------|
| Source protocole | HTTPS |
| Source nom d'hôte | bibliotheque.votre-domaine.fr |
| Source port | 443 |
| Destination protocole | HTTP |
| Destination nom d'hôte | localhost |
| Destination port | 8080 |

Activer **WebSocket** dans les en-têtes personnalisés si nécessaire.

## 6. Mise à jour

```bash
cd /volume1/docker/bibliotheque/app
git pull origin main
cd backend
docker compose -f docker-compose.prod.yml up --build -d
docker compose -f docker-compose.prod.yml exec app php bin/console doctrine:migrations:migrate -n --env=prod
```

## 7. Sauvegardes

Utiliser **Hyper Backup** (paquet Synology) pour sauvegarder :
- Le dossier `/volume1/docker/bibliotheque/`
- Le volume Docker `db_data` (données MariaDB)

Ou manuellement :

```bash
# Sauvegarde base de données
docker compose -f docker-compose.prod.yml exec db mysqldump -u biblio -p bibliotheque | gzip > backup_$(date +%Y%m%d).sql.gz
```

## Résumé des ports (Docker)

| Service | Port conteneur | Port NAS | Accès |
|---------|---------------|----------|-------|
| App (Apache) | 80 | 8080 | Via reverse proxy Synology |
| MariaDB | 3306 | — | Interne Docker uniquement |

---

# Gestion des secrets Symfony

L'application utilise le **vault Symfony Secrets** pour protéger les secrets cryptographiques (`APP_SECRET`, `JWT_PASSPHRASE`). Le vault est chiffré asymétriquement : les fichiers chiffrés et la clé publique sont committés, seule la clé de déchiffrement est sensible.

## Secrets concernés

| Secret | Stockage | Raison |
|--------|----------|--------|
| `APP_SECRET` | Vault prod | Cryptographique — CSRF, cookies, signatures |
| `JWT_PASSPHRASE` | Vault prod | Cryptographique — signature des tokens JWT |
| `GEMINI_API_KEY` | Variable d'env | Clé API, rotation facile |
| `GOOGLE_BOOKS_API_KEY` | Variable d'env | Clé API, rotation facile |
| `DATABASE_URL` | Variable d'env | Infrastructure, varie par environnement |

## Fichiers du vault

```
backend/config/secrets/prod/
├── prod.encrypt.public.php          # Clé publique (committée) — chiffre les secrets
├── prod.decrypt.private.php         # Clé privée (GITIGNORÉE) — déchiffre les secrets
├── prod.list.php                    # Liste des secrets (committée)
├── prod.APP_SECRET.*.php            # Secret chiffré (committé)
└── prod.JWT_PASSPHRASE.*.php        # Secret chiffré (committé)
```

## Déployer la clé de déchiffrement en production

### Méthode 1 : Variable d'environnement (recommandée pour Docker)

```bash
# Sur la machine de dev, extraire la valeur base64 :
ddev exec "cd backend && php -r 'echo base64_encode(include \"config/secrets/prod/prod.decrypt.private.php\");'"

# Définir dans .env.local sur le serveur :
SYMFONY_DECRYPTION_SECRET=valeur_extraite
```

### Méthode 2 : Fichier (recommandée pour serveur classique)

```bash
scp backend/config/secrets/prod/prod.decrypt.private.php user@serveur:~/app/backend/config/secrets/prod/
```

Symfony détecte automatiquement le fichier au runtime.

## Gérer les secrets (dev)

```bash
# Ajouter ou modifier un secret
ddev exec "cd backend && bin/console secrets:set NOM_DU_SECRET --env=prod"

# Lister les secrets
ddev exec "cd backend && bin/console secrets:list --env=prod"

# Révéler les valeurs
ddev exec "cd backend && bin/console secrets:list --reveal --env=prod"
```

Committer les fichiers chiffrés après modification :

```bash
git add backend/config/secrets/prod/
git commit -m "chore(secrets): mise à jour du vault prod"
```

## Protection anti-placeholder

Un `PlaceholderSecretChecker` vérifie au démarrage de l'application en production que `APP_SECRET` et `JWT_PASSPHRASE` ne contiennent pas les valeurs placeholder du fichier `.env`. Si détecté, une exception bloque le démarrage avec un message explicite.
