#!/bin/bash
set -o pipefail
# Script de backup automatique de la BDD — lancé par le planificateur DSM (root)

APP_DIR="/volume1/docker/bibliotheque"
BACKEND_DIR="${APP_DIR}/backend"
ENV_FILE="${BACKEND_DIR}/.env.nas"
BACKUP_DIR="/volume1/google drive/Backup/Bibliotheque"
LOG_DIR="/var/log/bibliotheque"
LOG_FILE="${LOG_DIR}/backup-$(date '+%Y-%m-%d').log"
RETENTION_DAYS=7

mkdir -p "$LOG_DIR"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

log "=== Début du backup ==="

# Crée le dossier de backup s'il n'existe pas
if [ ! -d "$BACKUP_DIR" ]; then
    mkdir -p "$BACKUP_DIR"
    log "Dossier de backup créé : ${BACKUP_DIR}"
fi

# Charge le mot de passe root MariaDB depuis .env.nas
if [ ! -f "$ENV_FILE" ]; then
    log "ERREUR: fichier ${ENV_FILE} introuvable"
    exit 1
fi

MYSQL_ROOT_PASSWORD=$(grep -E '^MYSQL_ROOT_PASSWORD=' "$ENV_FILE" | cut -d'=' -f2-)
if [ -z "$MYSQL_ROOT_PASSWORD" ]; then
    log "ERREUR: MYSQL_ROOT_PASSWORD non défini dans ${ENV_FILE}"
    exit 1
fi

# Dump de la base de données
DUMP_FILE="${BACKUP_DIR}/bibliotheque-$(date '+%Y-%m-%d_%H%M%S').sql.gz"

cd "$BACKEND_DIR" || { log "ERREUR: impossible d'accéder à ${BACKEND_DIR}"; exit 1; }

docker compose --env-file "$ENV_FILE" exec -T db \
    mariadb-dump -u root -p"${MYSQL_ROOT_PASSWORD}" --single-transaction --routines --triggers bibliotheque \
    2>> "$LOG_FILE" | gzip > "$DUMP_FILE"

if [ $? -ne 0 ] || [ ! -s "$DUMP_FILE" ]; then
    log "ERREUR: le dump a échoué"
    rm -f "$DUMP_FILE"
    exit 1
fi

DUMP_SIZE=$(du -h "$DUMP_FILE" | cut -f1)
log "Dump créé : ${DUMP_FILE} (${DUMP_SIZE})"

# Rotation : supprime les backups de plus de N jours
DELETED=$(find "$BACKUP_DIR" -name "bibliotheque-*.sql.gz" -mtime +${RETENTION_DAYS} -delete -print | wc -l)
if [ "$DELETED" -gt 0 ]; then
    log "Rotation : ${DELETED} backup(s) de plus de ${RETENTION_DAYS} jours supprimé(s)"
fi

log "=== Backup terminé ==="
