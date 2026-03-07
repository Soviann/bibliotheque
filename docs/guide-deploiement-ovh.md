# Déploiement serveur Linux (OVH / VPS)

Guide pas-à-pas pour déployer l'application sur un serveur Linux dédié ou VPS OVH, **sans Docker**.

---

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
GEMINI_API_KEYS=cle1,cle2,cle3
GOOGLE_BOOKS_API_KEY=votre_cle_google_books_optionnelle
OAUTH_GOOGLE_ID=votre_google_client_id.apps.googleusercontent.com
OAUTH_ALLOWED_EMAIL=votre_email@gmail.com
```

> **Note** : `APP_SECRET` et `JWT_PASSPHRASE` sont gérés par le vault Symfony Secrets. Il n'est pas nécessaire de les définir dans `.env.local`.

### Déployer la clé de déchiffrement du vault

**Méthode fichier** (recommandée pour serveur classique) :

```bash
# Depuis la machine de dev, copier la clé privée
scp backend/config/secrets/prod/prod.decrypt.private.php bibliotheque@serveur:~/app/backend/config/secrets/prod/
```

**Méthode variable d'environnement** (alternative) :

```bash
# Sur la machine de dev, extraire la valeur base64 :
ddev exec "cd backend && php -r 'echo base64_encode(include \"config/secrets/prod/prod.decrypt.private.php\");'"

# Ajouter dans ~/app/backend/.env.local :
SYMFONY_DECRYPTION_SECRET=valeur_extraite
```

### Initialiser le backend

```bash
cd ~/app/backend

# Compiler les fichiers d'environnement pour la production
composer dump-env prod

# Générer les clés JWT
php bin/console lexik:jwt:generate-keypair

# Exécuter les migrations
php bin/console doctrine:migrations:migrate -n

# Vider le cache
php bin/console cache:clear --env=prod

# Pas de création d'utilisateur manuelle : le premier login Google crée le compte automatiquement

# Créer les répertoires d'uploads
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

# Créer le fichier d'environnement frontend
echo "VITE_GOOGLE_CLIENT_ID=votre_google_client_id.apps.googleusercontent.com" > .env.local

# Installer les dépendances et construire
npm ci
npm run build
```

Le build produit les fichiers statiques dans `~/app/frontend/dist/`.

---

## 5. Configurer PHP-FPM

Créer `/etc/php/8.3/fpm/pool.d/bibliotheque.conf` :

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

    # API : /api/* → PHP-FPM (Symfony)
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
    add_header Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://accounts.google.com https://fonts.googleapis.com https://fonts.gstatic.com;" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;

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
```

---

## 9. Mise à jour

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

```bash
# Logs Symfony
tail -f /home/bibliotheque/app/backend/var/log/prod.log

# Logs nginx
tail -f /var/log/nginx/bibliotheque-access.log
tail -f /var/log/nginx/bibliotheque-error.log

# Logs PHP-FPM
tail -f /var/log/php-fpm/bibliotheque-error.log

# Vérifier les services
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

### Fail2ban

```bash
sudo apt install -y fail2ban
sudo systemctl enable fail2ban
```

### Sauvegardes

Script `~/backup.sh` :

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

## Résumé des ports

| Service | Port | Accès |
|---------|------|-------|
| nginx HTTP | 80 | Redirige vers HTTPS |
| nginx HTTPS | 443 | Application |
| MariaDB | 3306 | Local uniquement |
| PHP-FPM | socket Unix | Local uniquement |
