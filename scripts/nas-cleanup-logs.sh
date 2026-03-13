#!/bin/bash
# Nettoyage des logs Bibliotheque — lancé par le planificateur DSM (root)
# Supprime les fichiers .log de plus de 7 jours dans /var/log/bibliotheque/

LOG_DIR="/var/log/bibliotheque"
RETENTION_DAYS=7

if [ ! -d "$LOG_DIR" ]; then
    exit 0
fi

find "$LOG_DIR" -name "*.log" -mtime +${RETENTION_DAYS} -delete
