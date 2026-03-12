#!/bin/bash
# Script de mise à jour automatique — lancé par le planificateur DSM (root)

APP_DIR="/volume1/docker/bibliotheque"
BACKEND_DIR="${APP_DIR}/backend"
ENV_FILE="${BACKEND_DIR}/.env.nas"
LOG_DIR="/var/log/bibliotheque"
LOG_FILE="${LOG_DIR}/update-$(date '+%Y-%m-%d').log"

mkdir -p "$LOG_DIR"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

# Supprime les logs de plus de 7 jours
find "$LOG_DIR" -name "update-*.log" -mtime +7 -delete

log "=== Début de la mise à jour ==="

# Pull les dernières modifications
cd "$APP_DIR" || { log "ERREUR: impossible d'accéder à ${APP_DIR}"; exit 1; }
GIT_OUTPUT=$(git pull origin main 2>&1)
log "git pull: ${GIT_OUTPUT}"

# Si rien n'a changé, on s'arrête
if echo "$GIT_OUTPUT" | grep -q "Already up to date"; then
    log "Aucune modification, arrêt."
    exit 0
fi

# Rebuild et redémarrage
cd "$BACKEND_DIR" || { log "ERREUR: impossible d'accéder à ${BACKEND_DIR}"; exit 1; }
docker compose --env-file "$ENV_FILE" -f docker-compose.prod.yml up --build -d >> "$LOG_FILE" 2>&1
log "Conteneurs reconstruits."

# Attendre que la DB soit healthy
sleep 15

# Migrations
docker compose --env-file "$ENV_FILE" -f docker-compose.prod.yml exec -T php php bin/console doctrine:migrations:migrate -n --env=prod >> "$LOG_FILE" 2>&1
log "Migrations exécutées."

log "=== Mise à jour terminée ==="
