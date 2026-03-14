#!/bin/bash
# Nettoyage des logs Bibliotheque — lancé par le planificateur DSM (root)
# Supprime les fichiers .log de plus de 7 jours dans le dossier logs du projet

LOG_DIR="/volume1/docker/bibliotheque/logs"
RETENTION_DAYS=7

if [ ! -d "$LOG_DIR" ]; then
    exit 0
fi

find "$LOG_DIR" -name "*.log" -mtime +${RETENTION_DAYS} -delete
