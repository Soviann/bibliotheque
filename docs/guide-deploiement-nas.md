# Déploiement NAS Synology (Docker Compose)

Guide pas-à-pas pour déployer l'application sur un NAS Synology DS920+ via Docker Compose. Conçu pour être exécuté par un agent (Claude Code via SSH) ou manuellement.

---

## Prérequis

- Synology DSM 7.2+
- Docker (Container Manager) installé depuis le Centre de paquets
- Accès SSH activé (DSM > Panneau de configuration > Terminal & SNMP > Activer SSH)
- Git installé (via `opkg` ou SynoCommunity)
- Le dépôt contient les clés de chiffrement du vault Symfony (fichiers `config/secrets/prod/`)

---

## 1. Cloner le dépôt

```bash
# Se connecter en SSH au NAS (utilisateur admin)
ssh admin@nas-ip

# Créer le dossier de travail
sudo mkdir -p /volume1/docker/bibliotheque
sudo chown $(whoami):users /volume1/docker/bibliotheque
cd /volume1/docker/bibliotheque

# Cloner le dépôt
git clone https://github.com/Soviann/bibliotheque.git app
cd app
```

---

## 2. Configurer les variables d'environnement

Créer `backend/.env.local` avec les vraies valeurs :

```bash
cat > backend/.env.local << 'EOF'
MYSQL_PASSWORD=mot_de_passe_securise
MYSQL_ROOT_PASSWORD=mot_de_passe_root_securise
SYMFONY_DECRYPTION_SECRET=valeur_de_la_cle
GEMINI_API_KEY=votre_cle_gemini
GOOGLE_BOOKS_API_KEY=votre_cle_google_books
EOF
```

### Obtenir SYMFONY_DECRYPTION_SECRET

Sur la **machine de développement** (pas le NAS) :

```bash
ddev exec "cd backend && php -r 'echo base64_encode(include \"config/secrets/prod/prod.decrypt.private.php\");'"
```

Copier la sortie dans `SYMFONY_DECRYPTION_SECRET` du fichier `.env.local` ci-dessus.

---

## 3. Construire et démarrer

```bash
cd /volume1/docker/bibliotheque/app/backend

# Construire les images et démarrer les conteneurs
docker compose -f docker-compose.prod.yml up --build -d

# Vérifier que les 3 conteneurs tournent (nginx, php, db)
docker compose -f docker-compose.prod.yml ps
```

Attendre que la colonne `STATUS` affiche `healthy` pour le conteneur `db` (environ 30 secondes).

---

## 4. Initialiser l'application

```bash
cd /volume1/docker/bibliotheque/app/backend

# Générer les clés JWT
docker compose -f docker-compose.prod.yml exec php php bin/console lexik:jwt:generate-keypair --env=prod

# Exécuter les migrations
docker compose -f docker-compose.prod.yml exec php php bin/console doctrine:migrations:migrate -n --env=prod

# Créer le premier utilisateur
docker compose -f docker-compose.prod.yml exec php php bin/console app:create-user admin@bibliotheque.fr motdepasse --env=prod
```

---

## 5. Vérifier le fonctionnement

```bash
# Test rapide : la page d'accueil répond
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080
# Attendu : 200

# Test API : le login fonctionne
curl -s -X POST http://localhost:8080/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@bibliotheque.fr","password":"motdepasse"}' \
  | head -c 100
# Attendu : {"token":"eyJ..."}
```

L'application est accessible sur `http://nas-ip:8080`.

---

## 6. Configurer le reverse proxy Synology (HTTPS)

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
| Destination port | 8080 |

3. Onglet **En-tête personnalisé** : activer **WebSocket** (optionnel)
4. Configurer un certificat Let's Encrypt dans **Sécurité > Certificat** si nécessaire

L'application est maintenant accessible sur `https://bibliotheque.votre-domaine.fr`.

---

## 7. Tâches planifiées

Dans DSM > **Panneau de configuration > Planificateur de tâches** :

### Purger les séries supprimées (quotidien, 3h)

- Type : Script défini par l'utilisateur
- Planification : tous les jours à 03:00
- Commande :

```bash
cd /volume1/docker/bibliotheque/app/backend && docker compose -f docker-compose.prod.yml exec -T php php bin/console app:purge-deleted --env=prod
```

---

## 8. Mise à jour de l'application

```bash
cd /volume1/docker/bibliotheque/app

# Récupérer les dernières modifications
git pull origin main

# Reconstruire et redémarrer
cd backend
docker compose -f docker-compose.prod.yml up --build -d

# Exécuter les migrations si nécessaire
docker compose -f docker-compose.prod.yml exec php php bin/console doctrine:migrations:migrate -n --env=prod
```

---

## 9. Sauvegardes

### Hyper Backup (recommandé)

Configurer Hyper Backup pour sauvegarder :
- `/volume1/docker/bibliotheque/` (code source + .env.local)

### Sauvegarde manuelle de la base de données

```bash
cd /volume1/docker/bibliotheque/app/backend
docker compose -f docker-compose.prod.yml exec -T db mysqldump -u biblio -p"$(grep MYSQL_PASSWORD .env.local | head -1 | cut -d= -f2)" bibliotheque | gzip > /volume1/docker/bibliotheque/backup_$(date +%Y%m%d).sql.gz
```

### Restauration

```bash
cd /volume1/docker/bibliotheque/app/backend
gunzip -c /volume1/docker/bibliotheque/backup_YYYYMMDD.sql.gz | docker compose -f docker-compose.prod.yml exec -T db mysql -u biblio -p"$(grep MYSQL_PASSWORD .env.local | head -1 | cut -d= -f2)" bibliotheque
```

---

## 10. Dépannage

### Voir les logs

```bash
cd /volume1/docker/bibliotheque/app/backend

# Logs de tous les conteneurs
docker compose -f docker-compose.prod.yml logs

# Logs d'un service spécifique
docker compose -f docker-compose.prod.yml logs nginx
docker compose -f docker-compose.prod.yml logs php
docker compose -f docker-compose.prod.yml logs db

# Logs Symfony
docker compose -f docker-compose.prod.yml exec php cat var/log/prod.log
```

### Redémarrer les conteneurs

```bash
cd /volume1/docker/bibliotheque/app/backend
docker compose -f docker-compose.prod.yml restart
```

### Recréer complètement (sans perdre les données)

```bash
cd /volume1/docker/bibliotheque/app/backend
docker compose -f docker-compose.prod.yml down
docker compose -f docker-compose.prod.yml up --build -d
```

### Réinitialiser la base de données (DESTRUCTIF)

```bash
cd /volume1/docker/bibliotheque/app/backend
docker compose -f docker-compose.prod.yml down -v  # Supprime les volumes
docker compose -f docker-compose.prod.yml up --build -d
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
