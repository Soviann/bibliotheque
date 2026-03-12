# Runbook : Déploiement NAS Synology par Claude Code (SSH)

Runbook pour Claude Code. Commandes exactes, aucune interprétation nécessaire. Toutes les commandes Docker nécessitent `sudo`.

---

## Prérequis utilisateur

| Variable | Description |
|----------|-------------|
| `NAS_HOST` | IP ou hostname du NAS |
| `NAS_USER` | Utilisateur SSH |
| `NAS_PORT` | Port SSH (défaut 22) |
| `MYSQL_PASSWORD` | Générer avec `openssl rand -hex 24` |
| `MYSQL_ROOT_PASSWORD` | Générer avec `openssl rand -hex 24` |
| `OAUTH_GOOGLE_ID` | Google Cloud Console > ID client OAuth 2.0 |
| `OAUTH_ALLOWED_EMAIL` | Email Gmail autorisé |
| `GEMINI_API_KEYS` | Clés API Gemini (optionnel) |
| `GOOGLE_BOOKS_API_KEY` | Clé Google Books (optionnel) |
| `SERPER_API_KEY` | Clé Serper (optionnel) |

`SYMFONY_DECRYPTION_SECRET` est extrait automatiquement (étape 0).

---

## Étape 0 : Extraire SYMFONY_DECRYPTION_SECRET (machine de dev)

```bash
ddev exec "cd backend && php -r 'echo base64_encode(include \"config/secrets/prod/prod.decrypt.private.php\");'"
```

Stocker la sortie dans `DECRYPTION_SECRET`.

---

## Étape 1 : Connexion SSH

```bash
ssh -p ${NAS_PORT:-22} ${NAS_USER}@${NAS_HOST}
```

---

## Étape 2 : Cloner le dépôt

```bash
sudo mkdir -p /volume1/docker/bibliotheque && sudo chown $(whoami):users /volume1/docker/bibliotheque && cd /volume1/docker/bibliotheque && git clone git@github.com:Soviann/bibliotheque.git .
```

**Vérification** : `ls backend/docker-compose.prod.yml` doit exister.

---

## Étape 3 : Configurer Git pour root

```bash
sudo git config --global --add safe.directory /volume1/docker/bibliotheque
```

---

## Étape 4 : Créer .env.nas

```bash
cat > /volume1/docker/bibliotheque/backend/.env.nas << EOF
APP_PORT=8082
CORS_ALLOW_ORIGIN=${CORS_ALLOW_ORIGIN}
DEFAULT_URI=${DEFAULT_URI}
GEMINI_API_KEYS=${GEMINI_API_KEYS}
GOOGLE_BOOKS_API_KEY=${GOOGLE_BOOKS_API_KEY}
MYSQL_PASSWORD=${MYSQL_PASSWORD}
MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}
OAUTH_ALLOWED_EMAIL=${OAUTH_ALLOWED_EMAIL}
OAUTH_GOOGLE_ID=${OAUTH_GOOGLE_ID}
SERPER_API_KEY=${SERPER_API_KEY}
SYMFONY_DECRYPTION_SECRET=${DECRYPTION_SECRET}
EOF
```

**Vérification** : `cat backend/.env.nas` doit afficher les vraies valeurs.

---

## Étape 5 : Construire et démarrer

```bash
cd /volume1/docker/bibliotheque/backend && sudo docker compose --env-file .env.nas -f docker-compose.prod.yml up --build -d
```

**Vérification** : `sudo docker compose --env-file .env.nas -f docker-compose.prod.yml ps` — 3 conteneurs `Up`, `db` = `healthy`.

---

## Étape 6 : Initialiser

```bash
cd /volume1/docker/bibliotheque/backend
sudo docker compose --env-file .env.nas -f docker-compose.prod.yml exec php php bin/console lexik:jwt:generate-keypair --env=prod
sudo docker compose --env-file .env.nas -f docker-compose.prod.yml exec php php bin/console doctrine:migrations:migrate -n --env=prod
```

---

## Étape 7 : Vérifier

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8082
# Attendu : 200

curl -s -X POST http://localhost:8082/api/login/google -H "Content-Type: application/json" -d '{}' | head -c 100
# Attendu : {"error":"Paramètre \"credential\" manquant."}
```

---

## Résultat

Informer l'utilisateur :
1. App accessible sur `http://${NAS_HOST}:8082`
2. Configurer le reverse proxy dans DSM (HTTPS 443 → localhost:8082) + certificat Let's Encrypt
3. Configurer les tâches planifiées DSM (root) : mise à jour auto (04:00) + purge séries (03:00)
4. Les données persistent dans des volumes Docker

## Dépannage

- **"dubious ownership"** : `sudo git config --global --add safe.directory /volume1/docker/bibliotheque`
- **"PlaceholderSecretChecker"** : `SYMFONY_DECRYPTION_SECRET` incorrect dans `.env.nas`
- **"Malformed parameter url"** : caractère spécial dans `MYSQL_PASSWORD` — regénérer avec `openssl rand -hex 24`
- **"directory is not writable"** : `sudo docker compose --env-file .env.nas -f docker-compose.prod.yml exec php chown -R www-data:www-data /var/www/html/var`
