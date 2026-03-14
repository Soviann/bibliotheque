#!/bin/bash
# Script de mise à jour automatique — lancé par le planificateur DSM ou GitHub Actions (SSH)
# Déploie le dernier tag SemVer (vX.Y.Z) depuis le dépôt distant.

export PATH="/usr/local/bin:$PATH"

APP_DIR="/volume1/docker/bibliotheque"
BACKEND_DIR="${APP_DIR}/backend"
ENV_FILE="${BACKEND_DIR}/.env.nas"
LOG_DIR="${APP_DIR}/logs"
LOG_FILE="${LOG_DIR}/update-$(date '+%Y-%m-%d').log"

mkdir -p "$LOG_DIR"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

# Retourne le dernier tag SemVer (vX.Y.Z) trié par version.
latest_tag() {
    git -C "$APP_DIR" tag --sort=-v:refname | grep -E '^v[0-9]+\.[0-9]+\.[0-9]+$' | head -1
}

# Retourne le tag actuellement déployé (celui qui pointe sur HEAD, ou vide).
current_tag() {
    git -C "$APP_DIR" describe --tags --exact-match HEAD 2>/dev/null
}

# Tente un build et vérifie que les conteneurs démarrent correctement.
# Retourne 0 si succès, 1 si échec.
try_build() {
    local tag
    tag=$(current_tag)
    log "Tentative de build pour le tag ${tag:-$(git -C "$APP_DIR" rev-parse --short HEAD)}..."

    cd "$BACKEND_DIR" || { log "ERREUR: impossible d'accéder à ${BACKEND_DIR}"; return 1; }

    docker compose --env-file "$ENV_FILE" down >> "$LOG_FILE" 2>&1

    if ! docker compose --env-file "$ENV_FILE" up --build -d >> "$LOG_FILE" 2>&1; then
        log "ERREUR: docker compose up --build a échoué."
        return 1
    fi

    # Vérifier que tous les conteneurs sont running après un court délai
    sleep 10
    local not_running
    not_running=$(docker compose --env-file "$ENV_FILE" ps --format '{{.State}}' 2>/dev/null | grep -civ "running")

    if [ "$not_running" -gt 0 ]; then
        log "ERREUR: des conteneurs ne sont pas running."
        return 1
    fi

    log "Build réussi."
    return 0
}

log "=== Début de la mise à jour ==="

cd "$APP_DIR" || { log "ERREUR: impossible d'accéder à ${APP_DIR}"; exit 1; }

# Récupérer les tags distants
if ! git fetch --tags origin >> "$LOG_FILE" 2>&1; then
    log "ERREUR: git fetch --tags a échoué."
    exit 1
fi

TARGET_TAG=$(latest_tag)
CURRENT_TAG=$(current_tag)

if [ -z "$TARGET_TAG" ]; then
    log "ERREUR: aucun tag SemVer trouvé."
    exit 1
fi

if [ "$TARGET_TAG" = "$CURRENT_TAG" ]; then
    # Vérifier que les conteneurs tournent, sinon forcer un rebuild
    cd "$BACKEND_DIR" || { log "ERREUR: impossible d'accéder à ${BACKEND_DIR}"; exit 1; }
    RUNNING=$(docker compose --env-file "$ENV_FILE" ps --format '{{.State}}' 2>/dev/null | grep -ci "running" || true)
    if [ "$RUNNING" -ge 3 ]; then
        log "Déjà sur le tag ${TARGET_TAG}, conteneurs OK."
        exit 0
    fi
    log "Tag ${TARGET_TAG} déjà déployé mais conteneurs non running (${RUNNING}/3). Rebuild..."
fi

log "Mise à jour : ${CURRENT_TAG:-aucun tag} → ${TARGET_TAG}"

# Checkout du tag cible
git checkout "$TARGET_TAG" >> "$LOG_FILE" 2>&1

# Tentative de build avec le nouveau tag
if try_build; then
    # Attendre que la DB soit healthy
    sleep 15

    # Vider le cache Symfony en tant que www-data (le volume app_var persiste entre les rebuilds)
    docker compose --env-file "$ENV_FILE" exec -T -u www-data php php bin/console cache:clear --env=prod >> "$LOG_FILE" 2>&1
    log "Cache Symfony vidé."

    # Migrations
    docker compose --env-file "$ENV_FILE" exec -T php php bin/console doctrine:migrations:migrate -n --env=prod >> "$LOG_FILE" 2>&1
    log "Migrations exécutées."
    log "=== Mise à jour terminée (${TARGET_TAG}) ==="
    exit 0
fi

# Échec du build : rollback vers les tags précédents
log "Le build a échoué pour ${TARGET_TAG}, début du rollback..."

# Lister les tags SemVer par version décroissante, en excluant le tag qui vient d'échouer
PREVIOUS_TAGS=$(git -C "$APP_DIR" tag --sort=-v:refname | grep -E '^v[0-9]+\.[0-9]+\.[0-9]+$' | grep -v "^${TARGET_TAG}$" | head -5)

for tag in $PREVIOUS_TAGS; do
    log "Rollback vers ${tag}..."
    git checkout "$tag" >> "$LOG_FILE" 2>&1

    if try_build; then
        sleep 15
        # Vider le cache Symfony après rollback (en tant que www-data pour les permissions)
        docker compose --env-file "$ENV_FILE" exec -T -u www-data php php bin/console cache:clear --env=prod >> "$LOG_FILE" 2>&1
        log "Cache Symfony vidé après rollback."
        log "ATTENTION: rollback effectué — vérifier manuellement la cohérence des migrations si le tag annulé contenait des changements de schéma."
        docker compose --env-file "$ENV_FILE" exec -T php php bin/console doctrine:migrations:migrate -n --env=prod >> "$LOG_FILE" 2>&1
        log "Migrations exécutées après rollback."
        log "=== Mise à jour terminée (rollback vers ${tag}) ==="
        exit 0
    fi
done

log "ERREUR CRITIQUE: rollback échoué après avoir essayé les tags précédents. Intervention manuelle requise."
log "=== Mise à jour échouée ==="
exit 1
