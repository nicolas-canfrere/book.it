.DEFAULT_GOAL: help

.PHONY: $(filter-out vendor, $(shell awk -F: '/^[a-zA-Z0-9_%-]+:/ { print $$1 }' $(MAKEFILE_LIST) | sort | uniq))

INTERACTIVE := $(shell [ -t 0 ] && echo 1 || echo 0)
ifeq ($(INTERACTIVE), 1)
	DOCKER_FLAGS += -t
else
	DOCKER_COMPOSE_RUN_FLAGS += -T
endif

COMPOSER_HOME ?= ${HOME}/.composer
COMPOSER_CLI = docker run $(DOCKER_FLAGS) -i --rm \
	--env COMPOSER_HOME=${COMPOSER_HOME} \
	--volume ${COMPOSER_HOME}:${COMPOSER_HOME} \
	--volume ${PWD}:/app \
	--user $(shell id -u):$(shell id -g) \
	--workdir /app \
	composer:2.8.9

DOCKER_COMPOSE_FILE ?= compose.yaml
DOCKER_ENV_FILES = --env-file .env $(if $(wildcard .env.compose),--env-file .env.compose)
DOCKER_COMPOSE = docker compose -f $(DOCKER_COMPOSE_FILE) $(DOCKER_ENV_FILES)
KEYCLOAK_REALM ?= $(shell grep -m1 '^KEYCLOAK_REALM=' .env | cut -d= -f2)
DOCKER_COMPOSE_RUN = $(DOCKER_COMPOSE) --progress quiet run --rm --remove-orphans

help: ## Display this help
	@awk 'BEGIN {FS = ":.* ##"; printf "\n\033[1mUsage:\033[0m\n  make \033[32m<target>\033[0m\n"} /^[a-zA-Z_-]+:.* ## / { printf "  \033[33m%-25s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)

##@ Installation
install: vendor ## Install all necessary things

vendor: composer.json composer.lock
	@$(COMPOSER_CLI) install --ignore-platform-reqs

##@ CLI
composer-cli: ## Composer runtime. See https://getcomposer.org/doc/03-cli.md
	$(COMPOSER_CLI) /bin/sh

composer-req: ## Add a PHP package. Usage: make composer-req PACKAGES="vendor/package [--dev]"
	$(COMPOSER_CLI) require --ignore-platform-reqs $(PACKAGES)

php-cli: ## PHP runtime
	$(DOCKER_COMPOSE_RUN) php sh

##@ Database Utils
generate-migration: ## Generate a new migration.
	$(DOCKER_COMPOSE_RUN) php bin/console doctrine:migrations:generate

migrate: ## Run migrations. OPTIONS="-q --allow-no-migrations"
	$(DOCKER_COMPOSE_RUN) php bin/console doctrine:migrations:migrate -n $(OPTIONS)

fixtures: ## Load fixtures (truncates all tables first)
	$(DOCKER_COMPOSE_RUN) php bin/console app:fixtures:load

##@ Code analysis
static-code-analysis: ## Code analysis
	$(DOCKER_COMPOSE_RUN) --no-deps php sh -c "php bin/console cache:clear --env=test \
	&& php bin/console cache:warmup --env=test \
	&& php ./vendor/bin/phpstan analyse --memory-limit=512M"

apply-cs: ## Apply coding standards with PHP CS Fixer
	$(DOCKER_COMPOSE_RUN) --no-deps php vendor/bin/php-cs-fixer fix --show-progress=dots --diff --config=.php-cs-fixer.dist.php

deptrac: ## Check architectural layer dependencies + bounded context boundaries
	$(DOCKER_COMPOSE_RUN) --no-deps php vendor/bin/deptrac analyse --no-progress
	$(DOCKER_COMPOSE_RUN) --no-deps php vendor/bin/deptrac --config-file=deptrac-contexts.yaml analyse --no-progress

lint: static-code-analysis apply-cs deptrac ## Full code analysis (cs fixer, phpstan and deptrac)

##@ Tests
DOCKER_COMPOSE_TEST = docker compose --progress quiet -p bookit-test -f compose.test.yaml --env-file .env --env-file .env.compose

test: unit-test functional-test ## Run all tests unit/integration/functional

unit-test: ## Run unit tests
	@$(DOCKER_COMPOSE_TEST) run --rm --no-deps php-test vendor/bin/phpunit --stop-on-defect --group=unit --group=integration $(ARGS)

unit-test-quiet: ## Run unit tests silently
	@$(DOCKER_COMPOSE_TEST) run --rm --no-deps php-test sh -c \
		"vendor/bin/phpunit --no-progress --stop-on-defect --group=unit --group=integration $(ARGS) > /tmp/.phpunit_out 2>&1; \
		 CODE=$$?; \
		 awk '/^There (was|were) [0-9]/{p=1;next} /^OK /{print} /^FAILURES!/{p=2;print;next} p==2{print;p=0;next} p' /tmp/.phpunit_out; \
		 exit $$CODE"

functional-test: ## Run functional tests
	@$(DOCKER_COMPOSE_TEST) up -d
	@$(DOCKER_COMPOSE_TEST) run --rm php-test bin/console doctrine:migrations:migrate -n -q
	@$(DOCKER_COMPOSE_TEST) run --rm --no-deps php-test vendor/bin/phpunit --stop-on-defect --group=functional $(ARGS)
	@$(DOCKER_COMPOSE_TEST) down --remove-orphans -v

##@ Docker
keycloak-export: ## Export Keycloak realm config to .docker/keycloak/import/ (usage: make keycloak-export REALM=bookit)
	mkdir -p .docker/keycloak/import
	$(DOCKER_COMPOSE) exec keycloack /opt/keycloak/bin/kc.sh export \
		--dir /tmp/kc-export \
		$(if $(or $(REALM),$(KEYCLOAK_REALM)),--realm $(or $(REALM),$(KEYCLOAK_REALM))) \
		--users skip
	$(DOCKER_COMPOSE) cp keycloack:/tmp/kc-export/. .docker/keycloak/import/
	@echo "Realm exported to .docker/keycloak/import/"

up: ## Start all services (creates shared network if needed)
	docker network create bookit-nw 2>/dev/null || true
	$(DOCKER_COMPOSE) up -d
	$(MAKE) migrate

down: ## Stop all services
	$(DOCKER_COMPOSE) down --remove-orphans

##@ OpenApi doc
openapi: ## write openapi doc in yaml file at the root directory: openapi.yaml
	$(DOCKER_COMPOSE_RUN) --no-deps php bin/console nelmio:apidoc:dump --format=yaml > openapi.yaml

events: ## Generate domainevents.yaml from registered domain event listeners
	$(DOCKER_COMPOSE_RUN) --no-deps php bin/console app:events:catalog

contextmap: ## Generate contextmap.yaml and docs/context-map.md from source
	$(DOCKER_COMPOSE_RUN) --no-deps php bin/console app:contextmap:generate

contextmap-check: ## Validate contextmap.yaml against source code
	$(DOCKER_COMPOSE_RUN) --no-deps php bin/console app:contextmap:check
