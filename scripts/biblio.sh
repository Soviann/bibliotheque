#!/bin/bash
# Raccourcis de gestion pour le NAS Synology

APP_DIR="/volume1/docker/bibliotheque"
BACKEND_DIR="${APP_DIR}/backend"
ENV_FILE="${BACKEND_DIR}/.env.nas"
DC="docker compose --env-file ${ENV_FILE}"

cd "$BACKEND_DIR" || { echo "ERREUR: impossible d'accéder à ${BACKEND_DIR}"; exit 1; }

case "${1:-help}" in
    up)
        $DC up --build -d
        ;;
    down)
        $DC down
        ;;
    restart)
        $DC down
        $DC up --build -d
        ;;
    logs)
        $DC logs --tail=50 "${2:-php}"
        ;;
    migrate)
        $DC exec php php bin/console doctrine:migrations:migrate -n --env=prod
        ;;
    ps)
        $DC ps
        ;;
    shell)
        $DC exec php bash
        ;;
    console)
        shift
        $DC exec php php bin/console "$@" --env=prod
        ;;
    help|*)
        echo "Usage: biblio <commande>"
        echo ""
        echo "  up        Construire et démarrer les conteneurs"
        echo "  down      Arrêter les conteneurs"
        echo "  restart   Redémarrer (down + up --build)"
        echo "  logs [s]  Logs d'un service (défaut: php)"
        echo "  migrate   Exécuter les migrations"
        echo "  ps        État des conteneurs"
        echo "  shell     Shell dans le conteneur PHP"
        echo "  console   Commande Symfony (ex: biblio console cache:clear)"
        ;;
esac
