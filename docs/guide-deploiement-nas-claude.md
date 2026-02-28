# Runbook : Déploiement NAS Synology par Claude Code (SSH)

Ce document est un runbook destiné à Claude Code pour effectuer le déploiement complet de l'application sur un NAS Synology via SSH. Chaque étape est une commande exacte à exécuter. Aucune interprétation nécessaire.

---

## Prérequis

Avant de lancer ce runbook, l'utilisateur doit fournir :

| Variable | Description | Exemple |
|----------|-------------|---------|
| `NAS_HOST` | IP ou hostname du NAS | `192.168.1.100` |
| `NAS_USER` | Utilisateur SSH (admin ou sudo) | `admin` |
| `NAS_PORT` | Port SSH (défaut 22) | `22` |
| `MYSQL_PASSWORD` | Mot de passe MariaDB pour l'app | (généré) |
| `MYSQL_ROOT_PASSWORD` | Mot de passe root MariaDB | (généré) |
| `OAUTH_GOOGLE_ID` | ID client OAuth Google | `xxx.apps.googleusercontent.com` |
| `OAUTH_ALLOWED_EMAIL` | Email Gmail autorisé | `user@gmail.com` |
| `GEMINI_API_KEY` | Clé API Gemini (optionnel) | |
| `GOOGLE_BOOKS_API_KEY` | Clé API Google Books (optionnel) | |

La valeur de `SYMFONY_DECRYPTION_SECRET` est extraite automatiquement de la machine de dev (étape 0).

---

## Étape 0 : Extraire SYMFONY_DECRYPTION_SECRET (sur machine de dev)

```bash
ddev exec "cd backend && php -r 'echo base64_encode(include \"config/secrets/prod/prod.decrypt.private.php\");'"
```

Stocker la sortie dans la variable `DECRYPTION_SECRET`.

---

## Étape 1 : Connexion SSH au NAS

```bash
ssh -p ${NAS_PORT:-22} ${NAS_USER}@${NAS_HOST}
```

Toutes les commandes suivantes sont exécutées sur le NAS via SSH.

---

## Étape 2 : Créer le dossier et cloner le dépôt

```bash
sudo mkdir -p /volume1/docker/bibliotheque && sudo chown $(whoami):users /volume1/docker/bibliotheque && cd /volume1/docker/bibliotheque && git clone https://github.com/Soviann/bibliotheque.git app
```

**Vérification** : `ls /volume1/docker/bibliotheque/app/backend/docker-compose.prod.yml` doit exister.

---

## Étape 3 : Créer .env.local

```bash
cat > /volume1/docker/bibliotheque/app/backend/.env.local << EOF
MYSQL_PASSWORD=${MYSQL_PASSWORD}
MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}
SYMFONY_DECRYPTION_SECRET=${DECRYPTION_SECRET}
GEMINI_API_KEY=${GEMINI_API_KEY}
GOOGLE_BOOKS_API_KEY=${GOOGLE_BOOKS_API_KEY}
OAUTH_GOOGLE_ID=${OAUTH_GOOGLE_ID}
OAUTH_ALLOWED_EMAIL=${OAUTH_ALLOWED_EMAIL}
EOF
```

**Vérification** : `cat /volume1/docker/bibliotheque/app/backend/.env.local` doit afficher les vraies valeurs, pas les variables.

---

## Étape 4 : Construire et démarrer les conteneurs

```bash
cd /volume1/docker/bibliotheque/app/backend && docker compose -f docker-compose.prod.yml up --build -d
```

**Vérification** : attendre 30 secondes puis vérifier :

```bash
docker compose -f docker-compose.prod.yml ps
```

Les 3 conteneurs (`nginx`, `php`, `db`) doivent être `Up`. Le conteneur `db` doit être `healthy`.

Si `db` n'est pas encore `healthy`, attendre 15 secondes et revérifier.

---

## Étape 5 : Générer les clés JWT

```bash
cd /volume1/docker/bibliotheque/app/backend && docker compose -f docker-compose.prod.yml exec php php bin/console lexik:jwt:generate-keypair --env=prod
```

**Vérification** : la sortie doit indiquer que les clés ont été générées. Si les clés existent déjà, ajouter `--overwrite`.

---

## Étape 6 : Exécuter les migrations

```bash
cd /volume1/docker/bibliotheque/app/backend && docker compose -f docker-compose.prod.yml exec php php bin/console doctrine:migrations:migrate -n --env=prod
```

**Vérification** : pas d'erreur. La sortie indique les migrations exécutées ou "Already at the latest version".

---

## Étape 7 : Vérifier le fonctionnement

> **Note** : pas de création d'utilisateur manuelle. Le premier login Google crée le compte automatiquement.

### Test 1 : Page d'accueil

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080
```

**Attendu** : `200`

### Test 2 : Endpoint Google login

```bash
curl -s -X POST http://localhost:8080/api/login/google -H "Content-Type: application/json" -d '{}' | head -c 100
```

**Attendu** : `{"error":"Paramètre \"credential\" manquant."}` (confirme que l'endpoint répond)

---

## Résultat

Si les 3 tests passent, le déploiement est terminé. L'application est accessible sur `http://${NAS_HOST}:8080`.

Informer l'utilisateur :
1. L'application fonctionne sur le port 8080
2. Pour HTTPS : configurer le reverse proxy dans DSM > Panneau de configuration > Portail de connexion > Avancé > Proxy inversé
3. Les données persistent dans des volumes Docker (survivent aux rebuilds)
4. Pour mettre à jour : `git pull` + `docker compose up --build -d` + migrations

---

## Dépannage

### Les conteneurs ne démarrent pas

```bash
cd /volume1/docker/bibliotheque/app/backend && docker compose -f docker-compose.prod.yml logs --tail=50
```

### Erreur "secret" ou "placeholder" au démarrage PHP

Le `PlaceholderSecretChecker` bloque si les secrets ne sont pas déchiffrés. Vérifier que `SYMFONY_DECRYPTION_SECRET` est correct dans `.env.local`.

### Erreur de build frontend (npm)

```bash
cd /volume1/docker/bibliotheque/app/backend && docker compose -f docker-compose.prod.yml logs nginx --tail=50
```

Si erreur mémoire sur le NAS, vérifier la RAM disponible : `free -h`. Le build Node.js nécessite ~1 Go de RAM.

### Base de données inaccessible

```bash
cd /volume1/docker/bibliotheque/app/backend && docker compose -f docker-compose.prod.yml exec db mysql -u biblio -p"${MYSQL_PASSWORD}" -e "SELECT 1"
```
