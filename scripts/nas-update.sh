#!/bin/bash
# Script de mise à jour automatique — lancé par le planificateur DSM (root)

APP_DIR="/volume1/docker/bibliotheque"
BACKEND_DIR="${APP_DIR}/backend"
ENV_FILE="${BACKEND_DIR}/.env.nas"
LOG_DIR="/var/log/bibliotheque"
LOG_FILE="${LOG_DIR}/update-$(date '+%Y-%m-%d').log"
MAX_ROLLBACKS=5

mkdir -p "$LOG_DIR"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

# Tente un build et vérifie que les conteneurs démarrent correctement.
# Retourne 0 si succès, 1 si échec.
try_build() {
    local commit_sha
    commit_sha=$(git -C "$APP_DIR" rev-parse --short HEAD)
    log "Tentative de build pour le commit ${commit_sha}..."

    cd "$BACKEND_DIR" || { log "ERREUR: impossible d'accéder à ${BACKEND_DIR}"; return 1; }

    docker compose --env-file "$ENV_FILE" down >> "$LOG_FILE" 2>&1
    docker compose --env-file "$ENV_FILE" up --build -d >> "$LOG_FILE" 2>&1

    if [ $? -ne 0 ]; then
        log "ERREUR: docker compose up --build a échoué pour le commit ${commit_sha}."
        return 1
    fi

    # Vérifier que tous les conteneurs sont running après un court délai
    sleep 10
    local not_running
    not_running=$(docker compose --env-file "$ENV_FILE" ps --format '{{.State}}' 2>/dev/null | grep -cv "running")

    if [ "$not_running" -gt 0 ]; then
        log "ERREUR: des conteneurs ne sont pas running pour le commit ${commit_sha}."
        return 1
    fi

    log "Build réussi pour le commit ${commit_sha}."
    return 0
}

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

# Arrêt propre des conteneurs avant rebuild (évite l'alerte DSM "arrêt inattendu")
cd "$BACKEND_DIR" || { log "ERREUR: impossible d'accéder à ${BACKEND_DIR}"; exit 1; }
docker compose --env-file "$ENV_FILE" down >> "$LOG_FILE" 2>&1
log "Conteneurs arrêtés."

# Tentative de build avec le dernier code
if try_build; then
    # Attendre que la DB soit healthy
    sleep 15

    # Migrations
    docker compose --env-file "$ENV_FILE" exec -T php php bin/console doctrine:migrations:migrate -n --env=prod >> "$LOG_FILE" 2>&1
    log "Migrations exécutées."
    log "=== Mise à jour terminée ==="
    exit 0
fi

# Échec du build : rollback commit par commit (first-parent = une PR entière)
log "Le build a échoué, début du rollback automatique..."

rollback_count=0
while [ "$rollback_count" -lt "$MAX_ROLLBACKS" ]; do
    rollback_count=$((rollback_count + 1))
    log "Rollback ${rollback_count}/${MAX_ROLLBACKS}..."

    # Remonter au merge commit précédent (first-parent)
    cd "$APP_DIR" || { log "ERREUR: impossible d'accéder à ${APP_DIR}"; exit 1; }
    PREVIOUS_COMMIT=$(git log --first-parent --format='%H' -n 2 | tail -1)

    if [ -z "$PREVIOUS_COMMIT" ]; then
        log "ERREUR: impossible de trouver un commit précédent pour le rollback."
        break
    fi

    git checkout "$PREVIOUS_COMMIT" >> "$LOG_FILE" 2>&1
    log "Rollback vers ${PREVIOUS_COMMIT}."

    if try_build; then
        sleep 15
        docker compose --env-file "$ENV_FILE" exec -T php php bin/console doctrine:migrations:migrate -n --env=prod >> "$LOG_FILE" 2>&1
        log "Migrations exécutées après rollback."
        log "=== Mise à jour terminée (rollback vers $(git -C "$APP_DIR" rev-parse --short HEAD)) ==="
        exit 0
    fi
done

log "ERREUR CRITIQUE: rollback échoué après ${MAX_ROLLBACKS} tentatives. Intervention manuelle requise."
log "=== Mise à jour échouée ==="
exit 1
