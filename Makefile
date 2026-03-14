# ──────────────────────────────────────────────────
# Bibliothèque — Makefile
# ──────────────────────────────────────────────────
# Raccourcis pour les commandes courantes.
# Usage : ddev exec make <cible>   (ex. ddev exec make test)
#
# Ce Makefile est conçu pour être exécuté à l'intérieur
# du conteneur DDEV (via `ddev exec make ...` ou `ddev ssh`).
# Ne pas exécuter directement sur la machine hôte.
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
	$(MAKE) jwt db-migrate

prod: ## Déploiement prod (dépendances --no-dev + dump env + build + migrations + cache)
	$(MAKE) install-back-prod install-front-prod
	$(MAKE) dump-env APP_ENV=prod
	$(MAKE) build db-migrate cc

ci: ## Intégration continue (lint + tests)
	$(MAKE) lint
	$(MAKE) test

# ── Installation ──────────────────────────────────

.PHONY: install install-back install-back-prod install-front install-front-prod dump-env

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

dump-env: ## Compiler .env pour Symfony (utilise APP_ENV)
	cd $(BACK) && composer dump-env $(APP_ENV)

# ── Base de données ───────────────────────────────

.PHONY: db-diff db-migrate db-reset db-seed

db-diff: ## Générer une migration Doctrine
	$(MAKE) sf CMD="doctrine:migrations:diff -n"

db-migrate: ## Exécuter les migrations
	$(MAKE) sf CMD="doctrine:migrations:migrate -n"

db-reset: ## Recréer la base de données et jouer les migrations
	$(MAKE) sf CMD="doctrine:database:drop --force --if-exists"
	$(MAKE) sf CMD="doctrine:database:create"
	$(MAKE) sf CMD="doctrine:migrations:migrate -n"

db-seed: ## Charger les fixtures de test
	$(MAKE) sf CMD="doctrine:fixtures:load -n"

# ── Tests ─────────────────────────────────────────

.PHONY: test test-back test-front

test: ## Lancer tous les tests (backend + frontend)
	$(MAKE) test-back test-front

test-back: ## Lancer les tests PHPUnit
	cd $(BACK) && vendor/bin/phpunit

test-front: ## Lancer les tests Vitest
	cd $(FRONT) && npx vitest run

# ── Qualité de code ───────────────────────────────

.PHONY: lint lint-back lint-front phpstan cs cs-dry rector rector-dry

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

rector: ## Appliquer les refactorings Rector
	cd $(BACK) && vendor/bin/rector process

rector-dry: ## Prévisualiser les refactorings Rector (dry-run)
	cd $(BACK) && vendor/bin/rector process --dry-run

# ── Build ─────────────────────────────────────────

.PHONY: build serve-prod verify-build

build: ## Compiler le frontend pour la production
	cd $(FRONT) && npm run build

serve-prod: ## Compiler et servir le build prod (port 4173)
	$(MAKE) build
	cd $(FRONT) && npx vite preview --host 0.0.0.0 --port 4173

verify-build: ## Vérifier que le build prod ne contient pas de code de debug
	$(MAKE) build
	@cd $(FRONT) && ! grep -q "ReactQueryDevtools" dist/assets/*.js \
		&& printf "  $(GREEN)✓$(RESET) Pas de ReactQueryDevtools dans le bundle\n" \
		|| (printf "  $(CYAN)✗$(RESET) ReactQueryDevtools trouvé dans le bundle !\n" && exit 1)

# ── Symfony ───────────────────────────────────────

.PHONY: cc sf jwt

cc: ## Vider le cache Symfony
	$(MAKE) sf CMD="cache:clear"

sf: ## Lancer une commande Symfony (usage : make sf CMD="debug:router")
	cd $(BACK) && php bin/console $(CMD)

jwt: ## Générer les clés JWT
	$(MAKE) sf CMD="lexik:jwt:generate-keypair --skip-if-exists"

# ── Production ────────────────────────────────────

.PHONY: deploy

deploy: ## Déploie en production (docker-compose)
	cd $(BACK) && docker compose up --build -d

# ── Aide ──────────────────────────────────────────

.PHONY: help

help: ## Afficher cette aide
	@printf "\n$(CYAN)Bibliothèque$(RESET) — Commandes disponibles :\n\n"
	@grep -hE '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  $(GREEN)%-18s$(RESET) %s\n", $$1, $$2}'
	@printf "\n"
