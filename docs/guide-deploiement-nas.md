# Déploiement NAS Synology DS920+

Déploiement via Docker Compose en SSH. Toutes les commandes Docker nécessitent `sudo`.

---

## Prérequis

- Synology DSM 7.2+ avec Docker (Container Manager) installé
- Accès SSH activé (DSM > Panneau de configuration > Terminal & SNMP)
- Git via SynoCommunity (paquet **Git**, pas "Git Server")
- Clé SSH configurée sur GitHub pour le clone
- DDNS Synology ou nom de domaine pointant vers le NAS
- Ports 80 et 443 redirigés sur le routeur vers le NAS

### Mettre à jour Docker Compose

Docker Engine 24 (embarqué dans DSM) ne supporte pas le top-level `env_file`. Mettre à jour le plugin Compose :

```bash
sudo cp /usr/local/bin/docker-compose /usr/local/bin/docker-compose.bak
sudo curl -L "https://github.com/docker/compose/releases/download/v2.32.4/docker-compose-linux-x86_64" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose
docker compose version  # v2.32.4
```

### Configurer Git pour root

Le script de mise à jour automatique tourne en root (planificateur DSM). Git refuse de travailler dans un repo appartenant à un autre utilisateur :

```bash
sudo git config --global --add safe.directory /volume1/docker/bibliotheque
```

---

## 1. Cloner le dépôt

```bash
ssh utilisateur@nas-ip

sudo mkdir -p /volume1/docker/bibliotheque
sudo chown $(whoami):users /volume1/docker/bibliotheque
cd /volume1/docker/bibliotheque
git clone git@github.com:Soviann/bibliotheque.git .
```

---

## 2. Configurer les secrets

Créer `backend/.env.nas` (gitignored) — fichier de secrets pour Docker Compose :

```bash
cat > backend/.env.nas << 'EOF'
APP_PORT=8082
CORS_ALLOW_ORIGIN='^https://bibliotheque\.nasgits.synology\.fr$'
DEFAULT_URI=https://bibliotheque.nasgits.synology.me
GEMINI_API_KEYS=cle1,cle2,cle3
GOOGLE_BOOKS_API_KEY=votre_cle
MYSQL_PASSWORD=mot_de_passe_hex
MYSQL_ROOT_PASSWORD=mot_de_passe_root_hex
OAUTH_ALLOWED_EMAIL=votre_email@gmail.com
OAUTH_GOOGLE_ID=xxx.apps.googleusercontent.com
SERPER_API_KEY=votre_cle
SYMFONY_DECRYPTION_SECRET=valeur_de_la_cle
EOF
```

**Mots de passe MySQL** : pas de caractères spéciaux (`/`, `@`, `#`, `+`, `%`) — interpolés dans l'URL de connexion. Générer avec `openssl rand -hex 24`.

**SYMFONY_DECRYPTION_SECRET** : extraire sur la machine de dev :

```bash
ddev exec "cd backend && php -r 'echo base64_encode(include \"config/secrets/prod/prod.decrypt.private.php\");'"
```

**OAUTH_GOOGLE_ID** : [Google Cloud Console](https://console.cloud.google.com/) > ID client OAuth 2.0 (Application Web). Ajouter l'URL de prod dans les origines JavaScript autorisées.

---

## 3. Construire et démarrer

```bash
cd /volume1/docker/bibliotheque/backend
sudo docker compose --env-file .env.nas up --build -d
sudo docker compose --env-file .env.nas ps
```

Attendre que `db` affiche `healthy` (~30 secondes).

---

## 4. Initialiser

```bash
cd /volume1/docker/bibliotheque/backend
sudo docker compose --env-file .env.nas exec php php bin/console lexik:jwt:generate-keypair --env=prod
sudo docker compose --env-file .env.nas exec php php bin/console doctrine:migrations:migrate -n --env=prod
```

Pas de création d'utilisateur — le premier login Google crée le compte.

---

## 5. Reverse proxy et HTTPS

### Reverse proxy

DSM > **Panneau de configuration > Portail de connexion > Avancé > Proxy inversé** > **Créer** :

| Paramètre | Valeur |
|-----------|--------|
| Nom | Bibliotheque |
| Source protocole | HTTPS |
| Source nom d'hôte | bibliotheque.nasgits.synology.me |
| Source port | 443 |
| Destination protocole | HTTP |
| Destination nom d'hôte | localhost |
| Destination port | 8082 |

### Certificat Let's Encrypt

DSM > **Sécurité > Certificat > Ajouter** > **Obtenir un certificat de Let's Encrypt** avec le domaine. Associer au service Bibliotheque dans l'onglet **Paramètres**.

---

## 6. Tâches planifiées

DSM > **Panneau de configuration > Planificateur de tâches**, utilisateur **root** :

### Mise à jour automatique (quotidien, 04:00)

```bash
bash /volume1/docker/bibliotheque/scripts/nas-update.sh
```

Le script (`scripts/nas-update.sh`) : pull, rebuild si changements, migrations. Si le build échoue, rollback automatique par merge commit (`--first-parent`, max 5 tentatives) jusqu'à retrouver un build fonctionnel. Logs dans `/var/log/bibliotheque/update-YYYY-MM-DD.log` (rétention 7 jours).

### Backup de la BDD (quotidien, 02:00)

```bash
bash /volume1/docker/bibliotheque/scripts/nas-backup.sh
```

Le script (`scripts/nas-backup.sh`) : dump MariaDB compressé gzip dans `/volume1/google drive/Backup/Bibliotheque/`, rotation à 7 jours. Logs dans `/var/log/bibliotheque/backup-YYYY-MM-DD.log`.

### Purge des séries supprimées (quotidien, 03:00)

```bash
cd /volume1/docker/bibliotheque/backend && docker compose --env-file .env.nas exec -T php php bin/console app:purge-deleted --env=prod
```

### Nettoyage des logs (quotidien, 05:00)

```bash
bash /volume1/docker/bibliotheque/scripts/nas-cleanup-logs.sh
```

Le script (`scripts/nas-cleanup-logs.sh`) : supprime les fichiers `.log` de plus de 7 jours dans `/var/log/bibliotheque/`.

---

## 7. Mise à jour manuelle

```bash
cd /volume1/docker/bibliotheque && git pull
cd backend && sudo docker compose --env-file .env.nas up --build -d
sudo docker compose --env-file .env.nas exec php php bin/console doctrine:migrations:migrate -n --env=prod
```

---

## 8. Sauvegardes

### Hyper Backup

Sauvegarder `/volume1/docker/bibliotheque/` (code + `.env.nas` + volumes Docker).

### Backup automatique de la BDD

Le script `scripts/nas-backup.sh` est exécuté quotidiennement à 02:00 par le planificateur DSM (voir section 6). Les dumps sont stockés dans `/volume1/google drive/Backup/Bibliotheque/` avec rotation à 7 jours.

### Dump manuel de la BDD

```bash
bash /volume1/docker/bibliotheque/scripts/nas-backup.sh
```

### Restauration

```bash
cd /volume1/docker/bibliotheque/backend
export MYSQL_ROOT_PASSWORD=$(grep '^MYSQL_ROOT_PASSWORD=' .env.nas | cut -d'=' -f2-)
gunzip -c "/volume1/google drive/Backup/Bibliotheque/bibliotheque-YYYYMMDD_HHMMSS.sql.gz" | sudo docker compose --env-file .env.nas exec -T db mariadb -u root -p"${MYSQL_ROOT_PASSWORD}" bibliotheque
```

---

## 9. Dépannage

```bash
cd /volume1/docker/bibliotheque/backend

# Logs d'un service
sudo docker compose --env-file .env.nas logs php --tail=50
sudo docker compose --env-file .env.nas logs nginx --tail=50

# Redémarrer
sudo docker compose --env-file .env.nas restart

# Recréer (sans perdre les données)
sudo docker compose --env-file .env.nas down
sudo docker compose --env-file .env.nas up --build -d

# Réinitialiser la BDD (DESTRUCTIF)
sudo docker compose --env-file .env.nas down -v
sudo docker compose --env-file .env.nas up --build -d
# Puis refaire l'étape 4
```

### Erreurs connues

- **"dubious ownership"** sur git : `sudo git config --global --add safe.directory /volume1/docker/bibliotheque`
- **"PlaceholderSecretChecker"** : `SYMFONY_DECRYPTION_SECRET` manquant ou incorrect dans `.env.nas`
- **"Malformed parameter url"** : caractère spécial dans `MYSQL_PASSWORD`
- **"directory is not writable"** : `sudo docker compose --env-file .env.nas exec php chown -R www-data:www-data /var/www/html/var`

---

## Architecture

| Conteneur | Image | Port | Rôle |
|-----------|-------|------|------|
| nginx | nginxinc/nginx-unprivileged:alpine + frontend build | 8080 → 8082 | SPA React + proxy API + uploads |
| php | php:8.3-fpm + Symfony | 9000 (interne) | PHP-FPM, API |
| db | mariadb:10.11 | 3306 (interne) | Base de données |

| Volume | Usage |
|--------|-------|
| `uploads` | Couvertures uploadées |
| `media` | Thumbnails LiipImagine |
| `jwt_keys` | Clés JWT |
| `app_var` | Cache et logs Symfony |
| `db_data` | Données MariaDB |
