# Deploiement

Ce guide explique comment deployer Ma Bibliotheque BD en production sur deux environnements :
- [Hebergement OVH](#deploiement-sur-ovh)
- [NAS Synology DS920+](#deploiement-sur-synology-ds920)

---

## Prerequis communs

### Fichiers de configuration

Creez le fichier `.env.prod.local` a la racine :

```dotenv
# Cle secrete (obligatoire, generez-en une avec: openssl rand -hex 32)
APP_SECRET=votre_cle_secrete_de_32_caracteres_minimum

# Base de donnees (adapter selon l'environnement)
DATABASE_URL="mysql://user:password@host:3306/bibliotheque?serverVersion=10.11.0-MariaDB"

# Environnement
APP_ENV=prod
APP_DEBUG=0
```

---

# Deploiement sur OVH

Cette section couvre le deploiement sur un hebergement OVH (VPS, serveur dedie ou hebergement web Pro).

## Option 1 : VPS ou Serveur dedie OVH

### Prerequis

- VPS ou serveur dedie OVH avec Ubuntu 22.04+
- Acces SSH root
- Nom de domaine pointe vers le serveur

### 1. Configuration initiale du serveur

```bash
# Connexion SSH
ssh root@votre-serveur.ovh

# Mise a jour du systeme
apt update && apt upgrade -y

# Installation des dependances
apt install -y curl git unzip nginx mariadb-server

# Installation de PHP 8.3
apt install -y software-properties-common
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-xml php8.3-mbstring php8.3-curl php8.3-zip php8.3-intl php8.3-gd

# Verifier le support WebP dans GD (requis pour LiipImagineBundle)
php8.3 -r "echo json_encode(gd_info());" | grep -q '"WebP Support":true' && echo "WebP OK" || echo "WebP manquant — installer libwebp-dev et recompiler GD"

# Installation de Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
```

### 2. Configuration de MariaDB

```bash
# Securisation de MariaDB
mysql_secure_installation

# Creation de la base de donnees
mysql -u root -p
```

```sql
CREATE DATABASE bibliotheque CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'bibliotheque'@'localhost' IDENTIFIED BY 'votre_mot_de_passe';
GRANT ALL PRIVILEGES ON bibliotheque.* TO 'bibliotheque'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3. Deploiement de l'application

```bash
# Creation du repertoire
mkdir -p /var/www/bibliotheque
cd /var/www/bibliotheque

# Clonage du projet
git clone https://github.com/Soviann/bibliotheque.git .

# Configuration de l'environnement
cp .env.prod.example .env.prod.local
nano .env.prod.local
```

Contenu de `.env.prod.local` :

```dotenv
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=votre_cle_secrete_generee
DATABASE_URL="mysql://bibliotheque:votre_mot_de_passe@localhost:3306/bibliotheque?serverVersion=10.11.0-MariaDB"
```

```bash
# Installation des dependances
composer install --no-dev --optimize-autoloader

# Migrations
php bin/console doctrine:migrations:migrate -n

# Creation d'un utilisateur
php bin/console app:create-user admin@example.com motdepasse

# Cache et permissions
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
chown -R www-data:www-data var public/uploads public/media
chmod -R 755 var public/uploads public/media
```

### 4. Configuration Nginx

Creez `/etc/nginx/sites-available/bibliotheque` :

```nginx
server {
    listen 80;
    server_name bibliotheque.votre-domaine.fr;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name bibliotheque.votre-domaine.fr;

    root /var/www/bibliotheque/public;
    index index.php;

    # SSL (Let's Encrypt)
    ssl_certificate /etc/letsencrypt/live/bibliotheque.votre-domaine.fr/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/bibliotheque.votre-domaine.fr/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256;

    # Securite
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Assets statiques
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|webp|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Service Worker (pas de cache)
    location = /sw.js {
        add_header Cache-Control "no-cache";
    }

    # Manifest PWA
    location = /manifest.webmanifest {
        add_header Cache-Control "no-cache";
        add_header Content-Type "application/manifest+json";
    }

    # Application
    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }

    # Logs
    error_log /var/log/nginx/bibliotheque_error.log;
    access_log /var/log/nginx/bibliotheque_access.log;
}
```

```bash
# Activer le site
ln -s /etc/nginx/sites-available/bibliotheque /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx
```

### 5. Certificat SSL avec Let's Encrypt

```bash
# Installation de Certbot
apt install -y certbot python3-certbot-nginx

# Generation du certificat
certbot --nginx -d bibliotheque.votre-domaine.fr

# Renouvellement automatique (deja configure par Certbot)
systemctl status certbot.timer
```

### 6. Mises a jour

```bash
cd /var/www/bibliotheque
git pull origin main
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate -n
php bin/console cache:clear --env=prod
chown -R www-data:www-data var public/media
```

---

## Option 2 : Hebergement Web OVH (Pro ou Performance)

Pour les hebergements web mutualises OVH avec SSH.

### Prerequis

- Hebergement OVH Pro ou Performance (PHP 8.3, SSH)
- Extension GD avec support WebP activee (verifier dans le panneau PHP de l'espace client OVH)
- Acces FTP/SSH
- Base de donnees MySQL/MariaDB creee via l'espace client OVH

### 1. Configuration de la base de donnees

Dans l'espace client OVH :
1. Allez dans **Hebergements** > **Bases de donnees**
2. Creez une nouvelle base de donnees
3. Notez les informations de connexion

### 2. Deploiement via SSH

```bash
# Connexion SSH
ssh votre-login@ssh.cluster0XX.hosting.ovh.net

# Aller dans le repertoire web
cd www

# Cloner le projet (ou telecharger via FTP)
git clone https://github.com/Soviann/bibliotheque.git bibliotheque
cd bibliotheque

# Configuration
cp .env.prod.example .env.prod.local
nano .env.prod.local
```

Contenu de `.env.prod.local` :

```dotenv
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=votre_cle_secrete
DATABASE_URL="mysql://utilisateur:motdepasse@serveurbd.mysql.db:3306/nom_base?serverVersion=8.0"
```

**Note** : Les informations de connexion BDD sont visibles dans l'espace client OVH.

```bash
# Installation des dependances
php composer.phar install --no-dev --optimize-autoloader

# Migrations
php bin/console doctrine:migrations:migrate -n

# Creation utilisateur
php bin/console app:create-user admin@example.com motdepasse

# Cache
php bin/console cache:clear --env=prod
```

### 3. Configuration du multisite OVH

Dans l'espace client OVH :
1. **Hebergements** > **Multisite**
2. Ajoutez votre domaine avec le dossier racine : `www/bibliotheque/public`
3. Activez SSL (Let's Encrypt)

### 4. Fichier .htaccess

Creez `public/.htaccess` si necessaire :

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>

# Cache des assets
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
</IfModule>
```

---

# Deploiement sur Synology DS920+

Cette section couvre le deploiement sur un NAS Synology DS920+ avec Docker (Container Manager).

## Prerequis

- Synology DS920+ avec DSM 7.2+
- Package **Container Manager** installe
- Dossier partage pour les donnees (ex: `docker`)
- Acces administrateur

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│                   Synology DS920+                       │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐     │
│  │   Nginx     │  │  PHP-FPM    │  │  MariaDB    │     │
│  │  (port 80)  │──│  (app)      │──│  (database) │     │
│  └─────────────┘  └─────────────┘  └─────────────┘     │
│         │                │                │             │
│         ▼                ▼                ▼             │
│  ┌─────────────────────────────────────────────────┐   │
│  │              Volume partage                      │   │
│  │  /volume1/docker/bibliotheque/                  │   │
│  │  ├── app/          # Code source                │   │
│  │  ├── database/     # Donnees MariaDB            │   │
│  │  ├── media/        # Cache images LiipImagine   │   │
│  │  └── uploads/      # Couvertures                │   │
│  └─────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
```

## 1. Preparation des dossiers

Via **File Station** ou SSH, creez la structure :

```bash
# Connexion SSH au NAS
ssh admin@nas.local

# Creation des dossiers
mkdir -p /volume1/docker/bibliotheque/{app,database,media,uploads,nginx}
cd /volume1/docker/bibliotheque
```

## 2. Fichiers de configuration

### docker-compose.yml

Creez `/volume1/docker/bibliotheque/docker-compose.yml` :

```yaml
version: '3.8'

services:
  app:
    image: php:8.3-fpm-alpine
    container_name: bibliotheque-app
    volumes:
      - ./app:/var/www/html
      - ./media:/var/www/html/public/media
      - ./uploads:/var/www/html/public/uploads
      - ./php.ini:/usr/local/etc/php/conf.d/custom.ini:ro
    environment:
      APP_ENV: prod
      APP_DEBUG: '0'
    depends_on:
      - database
    restart: unless-stopped
    networks:
      - bibliotheque

  database:
    image: mariadb:10.11
    container_name: bibliotheque-db
    volumes:
      - ./database:/var/lib/mysql
    environment:
      MYSQL_ROOT_PASSWORD: root_password_securise
      MYSQL_DATABASE: bibliotheque
      MYSQL_USER: bibliotheque
      MYSQL_PASSWORD: password_securise
    restart: unless-stopped
    networks:
      - bibliotheque

  nginx:
    image: nginx:alpine
    container_name: bibliotheque-nginx
    ports:
      - "8080:80"
    volumes:
      - ./app:/var/www/html:ro
      - ./media:/var/www/html/public/media:ro
      - ./uploads:/var/www/html/public/uploads:ro
      - ./nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      - app
    restart: unless-stopped
    networks:
      - bibliotheque

networks:
  bibliotheque:
    driver: bridge
```

### Configuration Nginx

Creez `/volume1/docker/bibliotheque/nginx/default.conf` :

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php;

    # Logs
    error_log /var/log/nginx/error.log;
    access_log /var/log/nginx/access.log;

    # Assets statiques
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|webp|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Service Worker
    location = /sw.js {
        add_header Cache-Control "no-cache";
    }

    # Manifest PWA
    location = /manifest.webmanifest {
        add_header Cache-Control "no-cache";
        add_header Content-Type "application/manifest+json";
    }

    # Application
    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass app:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }
}
```

### Configuration PHP

Creez `/volume1/docker/bibliotheque/php.ini` :

```ini
memory_limit = 256M
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 60

; OPcache
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0
```

## 3. Deploiement du code

```bash
cd /volume1/docker/bibliotheque/app

# Cloner le projet
git clone https://github.com/Soviann/bibliotheque.git .

# Configuration de l'environnement
cat > .env.prod.local << 'EOF'
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=votre_cle_secrete_generee_avec_openssl
DATABASE_URL="mysql://bibliotheque:password_securise@database:3306/bibliotheque?serverVersion=10.11.0-MariaDB"
EOF
```

## 4. Demarrage avec Container Manager

### Via l'interface DSM

1. Ouvrez **Container Manager**
2. Allez dans **Projet**
3. Cliquez sur **Creer**
4. Selectionnez le dossier `/docker/bibliotheque`
5. DSM detecte automatiquement le `docker-compose.yml`
6. Cliquez sur **Suivant** puis **Terminer**

### Via SSH

```bash
cd /volume1/docker/bibliotheque
docker compose up -d
```

## 5. Installation des dependances PHP

Le container PHP de base n'a pas les extensions necessaires. Creez un Dockerfile personnalise.

### Dockerfile

Creez `/volume1/docker/bibliotheque/Dockerfile` :

```dockerfile
FROM php:8.3-fpm-alpine

# Extensions PHP
RUN apk add --no-cache \
    icu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    intl \
    zip \
    gd \
    opcache

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Permissions
RUN chown -R www-data:www-data /var/www/html
```

Modifiez `docker-compose.yml` pour utiliser ce Dockerfile :

```yaml
services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    # ... reste de la configuration
```

Puis reconstruisez :

```bash
docker compose build
docker compose up -d
```

## 6. Initialisation de l'application

```bash
# Entrer dans le container
docker exec -it bibliotheque-app sh

# Installer les dependances
composer install --no-dev --optimize-autoloader

# Migrations
php bin/console doctrine:migrations:migrate -n

# Creer un utilisateur
php bin/console app:create-user admin@example.com motdepasse

# Cache
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# Permissions
chown -R www-data:www-data var public/uploads public/media

# Sortir du container
exit
```

## 7. Configuration du reverse proxy Synology

Pour acceder via HTTPS avec un nom de domaine.

### Via le Portail des applications

1. **Panneau de configuration** > **Portail de connexion** > **Avance**
2. Onglet **Proxy inverse**
3. Cliquez sur **Creer**

Configuration :
| Champ | Valeur |
|-------|--------|
| Nom | Bibliotheque |
| Source - Protocole | HTTPS |
| Source - Nom d'hote | bibliotheque.votre-domaine.fr |
| Source - Port | 443 |
| Destination - Protocole | HTTP |
| Destination - Nom d'hote | localhost |
| Destination - Port | 8080 |

4. Activez **HSTS** si desire
5. Configurez le certificat SSL (Let's Encrypt via DSM)

### Certificat SSL

1. **Panneau de configuration** > **Securite** > **Certificat**
2. **Ajouter** > **Ajouter un nouveau certificat**
3. **Obtenir un certificat de Let's Encrypt**
4. Entrez votre domaine : `bibliotheque.votre-domaine.fr`

## 8. Sauvegardes automatiques

### Via Hyper Backup

1. Ouvrez **Hyper Backup**
2. Creez une nouvelle tache de sauvegarde
3. Selectionnez le dossier `/docker/bibliotheque`
4. Configurez la planification (quotidienne recommandee)

### Script de sauvegarde manuel

Creez `/volume1/docker/bibliotheque/backup.sh` :

```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/volume1/backups/bibliotheque"

mkdir -p "$BACKUP_DIR"

# Base de donnees
docker exec bibliotheque-db mysqldump -u bibliotheque -ppassword_securise bibliotheque > "$BACKUP_DIR/db_$DATE.sql"

# Uploads
tar -czf "$BACKUP_DIR/uploads_$DATE.tar.gz" -C /volume1/docker/bibliotheque uploads

# Nettoyage (garder 7 jours)
find "$BACKUP_DIR" -mtime +7 -delete

echo "Sauvegarde terminee: $DATE"
```

```bash
chmod +x /volume1/docker/bibliotheque/backup.sh
```

Planifiez via **Panneau de configuration** > **Planificateur de taches**.

## 9. Mises a jour

```bash
cd /volume1/docker/bibliotheque/app

# Recuperer les modifications
git pull origin main

# Reconstruire si necessaire
cd ..
docker compose build
docker compose up -d

# Migrations
docker exec -it bibliotheque-app php bin/console doctrine:migrations:migrate -n

# Cache
docker exec -it bibliotheque-app php bin/console cache:clear --env=prod
```

---

## Comparaison des options

| Critere | OVH VPS | OVH Hebergement Web | Synology DS920+ |
|---------|---------|---------------------|-----------------|
| Cout mensuel | ~5-15€ | ~5-10€ | 0€ (materiel amorti) |
| Performance | Bonne | Moyenne | Bonne |
| Controle | Total | Limite | Total |
| Maintenance | Vous | OVH | Vous |
| Disponibilite | 99.9% | 99.9% | Depend du reseau local |
| Acces externe | Natif | Natif | Necessite port forwarding ou VPN |
| Sauvegardes | A configurer | Incluses | Hyper Backup |
| SSL | Let's Encrypt | Inclus | Let's Encrypt |

---

## Depannage

### Problemes courants OVH

| Probleme | Solution |
|----------|----------|
| Erreur 500 | Verifier `var/log/prod.log` et permissions |
| BDD inaccessible | Verifier `DATABASE_URL` et firewall |
| Assets non charges | Verifier la configuration Nginx/Apache |

### Problemes courants Synology

| Probleme | Solution |
|----------|----------|
| Container ne demarre pas | `docker compose logs` |
| Permission denied | `chown -R www-data:www-data var` |
| Port deja utilise | Changer le port dans `docker-compose.yml` |
| Acces externe impossible | Configurer le port forwarding sur le routeur |

---

## Etapes suivantes

- [Architecture](../architecture/README.md) - Comprendre l'application
- [Guide de developpement](../developpement/README.md) - Contribuer
