#!/bin/bash
# Diagnostics Bibliotheque — collecte tous les logs de crash pertinents
# Lancé manuellement via SSH ou automatiquement par GitHub Actions en cas d'échec de déploiement

export PATH="/usr/local/bin:$PATH"

APP_DIR="/volume1/docker/bibliotheque"
BACKEND_DIR="${APP_DIR}/backend"
ENV_FILE="${BACKEND_DIR}/.env.nas"
LOG_DIR="${APP_DIR}/logs"
LOG_FILE="${LOG_DIR}/diagnostics-$(date '+%Y-%m-%d_%H%M%S').log"
LINES=100

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --lines) LINES="$2"; shift 2 ;;
        *) shift ;;
    esac
done

mkdir -p "$LOG_DIR"

# Tout envoyer vers stdout ET le fichier log (même pattern que nas-update.sh)
exec > >(tee -a "$LOG_FILE") 2>&1

section() {
    echo ""
    echo "============================================================"
    echo "=== $1"
    echo "============================================================"
    echo ""
}

run_section() {
    local name="$1"
    shift
    section "$name"
    if ! "$@" 2>&1; then
        echo "[AVERTISSEMENT] Section '${name}' a échoué, poursuite du diagnostic..."
    fi
}

# Start
echo "=== Diagnostic Bibliotheque — $(date '+%Y-%m-%d %H:%M:%S') ==="
echo ""

cd "$BACKEND_DIR" || { echo "ERREUR: impossible d'accéder à ${BACKEND_DIR}"; exit 1; }

# 1. Container status
run_section "ÉTAT DES CONTENEURS" docker compose --env-file "$ENV_FILE" ps -a

# 2. Healthcheck results
section "HEALTHCHECKS"
for service in php db; do
    container=$(docker compose --env-file "$ENV_FILE" ps -q "$service" 2>/dev/null)
    if [ -n "$container" ]; then
        echo "--- ${service} ---"
        health_json=$(docker inspect --format='{{json .State.Health}}' "$container" 2>/dev/null)
        if [ -n "$health_json" ]; then
            echo "$health_json" | python3 -m json.tool 2>/dev/null || echo "$health_json"
        else
            echo "(pas de healthcheck ou conteneur arrêté)"
        fi
        echo ""
    else
        echo "--- ${service} --- (conteneur introuvable)"
    fi
done

# 3. Docker logs per service
for service in nginx php db; do
    run_section "LOGS DOCKER: ${service} (${LINES} dernières lignes)" \
        docker compose --env-file "$ENV_FILE" logs --tail="$LINES" --no-color "$service"
done

# 4. Symfony prod.log
section "SYMFONY PROD.LOG (${LINES} dernières lignes)"
docker compose --env-file "$ENV_FILE" exec -T php sh -c "cat var/log/prod.log 2>/dev/null | tail -${LINES}" 2>&1 || echo "(fichier prod.log introuvable ou conteneur arrêté)"

# 5. OOM / kill events (best-effort)
section "OOM / KILL (dmesg)"
dmesg 2>/dev/null | grep -iE 'oom|killed' | tail -20 || echo "(dmesg indisponible ou aucun événement)"

# 6. Disk space
run_section "ESPACE DISQUE" df -h /volume1

# 7. Last update log
section "DERNIER LOG DE MISE À JOUR"
LATEST_UPDATE_LOG=$(ls -t "${LOG_DIR}"/update-*.log 2>/dev/null | head -1)
if [ -n "$LATEST_UPDATE_LOG" ]; then
    echo "Fichier: ${LATEST_UPDATE_LOG}"
    echo ""
    tail -"$LINES" "$LATEST_UPDATE_LOG"
else
    echo "(aucun log de mise à jour trouvé)"
fi

echo ""
echo "=== Fin du diagnostic — $(date '+%Y-%m-%d %H:%M:%S') ==="
