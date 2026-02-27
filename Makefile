# ──────────────────────────────────────────────────
# Bibliothèque — Makefile
# ──────────────────────────────────────────────────
# Raccourcis pour les commandes courantes.
# Usage : make <cible>   (ex. make test, make lint)
# ──────────────────────────────────────────────────

include backend/.env

# Si ENV est défini, inclure le fichier d'environnement correspondant
ifdef ENV
ifneq ("$(wildcard backend/.env.$(ENV))","")
	include backend/.env.$(ENV)
endif
endif

# .env.local prévaut toujours (chargé en dernier)
ifneq ("$(wildcard backend/.env.local)","")
	include backend/.env.local
endif

.DEFAULT_GOAL := help

# ── Couleurs ──────────────────────────────────────
CYAN  := \033[36m
GREEN := \033[32m
RESET := \033[0m

# ── Chemins ─────────────────────────────────────
BACK  := backend
FRONT := frontend

# ── Workflows ─────────────────────────────────────

.PHONY: dev prod ci

dev: ## Premier lancement dev (dépendances + migrations)
	$(MAKE) install
	$(MAKE) db-migrate

prod: ## Déploiement prod (dépendances --no-dev + build + migrations + cache)
	$(MAKE) install-back-prod install-front-prod
	$(MAKE) build db-migrate cc

ci: ## Intégration continue (lint + tests)
	$(MAKE) lint
	$(MAKE) test

# ── Installation ──────────────────────────────────

.PHONY: install install-back install-back-prod install-front install-front-prod

install: ## Installer toutes les dépendances (backend + frontend)
	$(MAKE) install-back install-front

install-back: ## Installer les dépendances Composer
	cd $(BACK) && composer install

install-back-prod: ## Installer les dépendances Composer (sans dev, optimisé)
	cd $(BACK) && composer install --no-dev --optimize-autoloader

install-front: ## Installer les dépendances npm
	cd $(FRONT) && npm install

install-front-prod: ## Installer les dépendances npm (prod, lockfile exact)
	cd $(FRONT) && npm ci

# ── Base de données ───────────────────────────────

.PHONY: db-diff db-migrate db-reset

db-diff: ## Générer une migration Doctrine
	$(MAKE) sf CMD="doctrine:migrations:diff -n"

db-migrate: ## Exécuter les migrations
	$(MAKE) sf CMD="doctrine:migrations:migrate -n"

db-reset: ## Recréer la base de données et jouer les migrations
	$(MAKE) sf CMD="doctrine:database:drop --force --if-exists"
	$(MAKE) sf CMD="doctrine:database:create"
	$(MAKE) sf CMD="doctrine:migrations:migrate -n"

# ── Tests ─────────────────────────────────────────

.PHONY: test test-back test-front

test: ## Lancer tous les tests (backend + frontend)
	$(MAKE) test-back test-front

test-back: ## Lancer les tests PHPUnit
	cd $(BACK) && vendor/bin/phpunit

test-front: ## Lancer les tests Vitest
	cd $(FRONT) && npx vitest run

# ── Qualité de code ───────────────────────────────

.PHONY: lint lint-back lint-front phpstan cs cs-dry

lint: ## Vérifier la qualité (PHPStan + CS Fixer dry-run + TypeScript)
	$(MAKE) lint-back lint-front

lint-back: ## Vérifier le backend (PHPStan + CS Fixer dry-run)
	$(MAKE) phpstan cs-dry

lint-front: ## Vérifier le frontend (TypeScript)
	cd $(FRONT) && npx tsc --noEmit

phpstan: ## Lancer PHPStan (analyse statique PHP)
	cd $(BACK) && vendor/bin/phpstan analyse

cs-dry: ## Vérifier le style PHP (dry-run, sans modifier)
	cd $(BACK) && vendor/bin/php-cs-fixer fix --dry-run --diff

cs: ## Corriger le style PHP (modifie les fichiers)
	cd $(BACK) && vendor/bin/php-cs-fixer fix

# ── Build ─────────────────────────────────────────

.PHONY: build

build: ## Compiler le frontend pour la production
	cd $(FRONT) && npm run build

# ── Réseau ────────────────────────────────────────

.PHONY: proxy proxy-stop

proxy: ## Expose le site sur le port 8080 (accès réseau local)
	@HTTPS_PORT=$$(ddev describe -j 2>/dev/null \
		| python3 -c "import sys,json; urls=json.load(sys.stdin)['raw']['httpsURLs']; print([u.split(':')[-1] for u in urls if '127.0.0.1' in u][0])"); \
	echo "socat: 0.0.0.0:8080 → 127.0.0.1:$$HTTPS_PORT"; \
	socat TCP-LISTEN:8080,fork,reuseaddr,bind=0.0.0.0 TCP:127.0.0.1:$$HTTPS_PORT

proxy-stop: ## Arrête le proxy socat
	@pkill -f 'socat.*TCP-LISTEN:8080' 2>/dev/null && echo "socat arrêté" || echo "aucun socat en cours"

# ── Symfony ───────────────────────────────────────

.PHONY: cc sf

cc: ## Vider le cache Symfony
	$(MAKE) sf CMD="cache:clear"

sf: ## Lancer une commande Symfony (usage : make sf CMD="debug:router")
	cd $(BACK) && php bin/console $(CMD)

# ── Production ────────────────────────────────────

.PHONY: deploy

deploy: ## Déploie en production (docker-compose)
	cd $(BACK) && docker compose -f docker-compose.prod.yml up --build -d

# ── Aide ──────────────────────────────────────────

.PHONY: help

help: ## Afficher cette aide
	@printf "\n$(CYAN)Bibliothèque$(RESET) — Commandes disponibles :\n\n"
	@grep -hE '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  $(GREEN)%-18s$(RESET) %s\n", $$1, $$2}'
	@printf "\n"
