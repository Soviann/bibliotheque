include .env

# Si ENV est défini, inclure le fichier d'environnement correspondant
ifdef ENV
ifneq ("$(wildcard .env.$(ENV))","")
	include .env.$(ENV)
endif
endif

# .env.local prévaut toujours (chargé en dernier)
ifneq ("$(wildcard .env.local)","")
	include .env.local
endif

.PHONY: help build start stop proxy proxy-stop test test-php test-js test-js-watch \
	cc migrate migration lint deploy

.DEFAULT_GOAL := help

# Couleurs
GREEN  := \033[32m
YELLOW := \033[33m
RESET  := \033[0m

## —— Bibliotheqe Makefile ——————————————————————————————————————

help: ## Affiche cette aide
	@grep -E '(^[a-zA-Z_-]+:.*?##.*$$)|(^## )' Makefile \
		| awk 'BEGIN {FS = ":.*?## "} /^## /{printf "\n$(YELLOW)%s$(RESET)\n", substr($$0,4)} /^[a-zA-Z_-]+:/{printf "  $(GREEN)%-15s$(RESET) %s\n", $$1, $$2}'

## —— DDEV ——————————————————————————————————————————————————————

build: ## Build complet du projet en local
	ddev start
	ddev composer install
	ddev exec npm install
	ddev exec bin/console doctrine:migrations:migrate -n
	ddev exec bin/console asset-map:compile

start: ## Démarre DDEV
	ddev start

stop: ## Arrête DDEV
	ddev stop

## —— Réseau ————————————————————————————————————————————————————

proxy: ## Expose le site sur le port 8080 (accès réseau local)
	@HTTPS_PORT=$$(ddev describe -j 2>/dev/null \
		| python3 -c "import sys,json; urls=json.load(sys.stdin)['raw']['httpsURLs']; print([u.split(':')[-1] for u in urls if '127.0.0.1' in u][0])"); \
	echo "socat: 0.0.0.0:8080 → 127.0.0.1:$$HTTPS_PORT"; \
	socat TCP-LISTEN:8080,fork,reuseaddr,bind=0.0.0.0 TCP:127.0.0.1:$$HTTPS_PORT

proxy-stop: ## Arrête le proxy socat
	@pkill -f 'socat.*TCP-LISTEN:8080' 2>/dev/null && echo "socat arrêté" || echo "aucun socat en cours"

## —— Tests —————————————————————————————————————————————————————

test: test-php test-js ## Lance tous les tests

test-php: ## Lance les tests PHP (PHPUnit)
	ddev exec bin/phpunit

test-js: ## Lance les tests JS (Vitest)
	ddev exec npm test

test-js-watch: ## Lance les tests JS en mode watch
	ddev exec npm run test:watch

## —— Qualité ———————————————————————————————————————————————————

lint: ## Lance PHP-CS-Fixer et PHPStan
	ddev exec vendor/bin/php-cs-fixer fix --dry-run --diff
	ddev exec vendor/bin/phpstan analyse

## —— Symfony ———————————————————————————————————————————————————

cc: ## Vide le cache Symfony
	ddev exec bin/console cache:clear

migrate: ## Exécute les migrations en attente
	ddev exec bin/console doctrine:migrations:migrate -n

migration: ## Génère une migration depuis les diff d'entités
	ddev exec bin/console doctrine:migrations:diff -n

## —— Production ————————————————————————————————————————————————

deploy: ## Déploie en production (docker-compose)
	docker compose -f docker-compose.prod.yml up --build -d
