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

##@ Code analysis
static-code-analysis: ## Code analysis
	$(DOCKER_COMPOSE_RUN) --no-deps php sh -c "php bin/console cache:clear --env=test \
	&& php bin/console cache:warmup --env=test \
	&& php ./vendor/bin/phpstan analyse --memory-limit=512M"

apply-cs: ## Apply coding standards with PHP CS Fixer
	$(DOCKER_COMPOSE_RUN) --no-deps php vendor/bin/php-cs-fixer fix --show-progress=dots --diff --config=.php-cs-fixer.dist.php

lint: static-code-analysis apply-cs ## Full code analysis

##@ Tests
unit-test: DOCKER_COMPOSE_FILE=compose.test.yaml
unit-test: ## Run unit tests
	@$(DOCKER_COMPOSE_RUN) --no-deps php vendor/bin/phpunit $(ARGS)

unit-test-quiet: DOCKER_COMPOSE_FILE=compose.test.yaml
unit-test-quiet: ## Run unit tests silently
	@$(DOCKER_COMPOSE_RUN) --no-deps php sh -c \
		"vendor/bin/phpunit --no-progress $(ARGS) > /tmp/.phpunit_out 2>&1; \
		 CODE=$$?; \
		 awk '/^There (was|were) [0-9]/{p=1;next} /^OK /{print} /^FAILURES!/{p=2;print;next} p==2{print;p=0;next} p' /tmp/.phpunit_out; \
		 exit $$CODE"

##@ Docker
up: ## Start all services (creates shared network if needed)
	docker network create bookit-nw 2>/dev/null || true
	$(DOCKER_COMPOSE) up -d
	#$(MAKE) migrate

down: ## Stop all services
	$(DOCKER_COMPOSE) down --remove-orphans
