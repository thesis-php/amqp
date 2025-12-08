SHELL := /bin/bash

DOCKER ?= docker
DOCKER_COMPOSE ?= $(DOCKER) compose $(shell test -f .env.local && echo '--env-file .env --env-file .env.local')
export CONTAINER_USER ?= $(shell id -u):$(shell id -g)

RUN ?= $(if $(INSIDE_DEVCONTAINER),,$(DOCKER_COMPOSE) run --rm php)
COMPOSER ?= $(RUN) composer

##
## Project
## -----

t: terminal
terminal: var ## Start a terminal inside the php container
	@$(if $(INSIDE_CONTAINER),echo 'Already inside docker container.'; exit 1,)
	$(DOCKER_COMPOSE) run --rm $(ARGS) php bash
.PHONY: t terminal

run: ## Run a command using the php container: `make run CMD='php --version'`
	$(RUN) $(CMD)
.PHONY: run

up: ## Docker compose up
	$(DOCKER_COMPOSE) up --remove-orphans --build --detach $(ARGS)
.PHONY: up

down: ## Docker compose down
	$(DOCKER_COMPOSE) down --remove-orphans $(ARGS)
.PHONY: down

dc: docker-compose
docker-compose: ## Run docker compose command: `make dc CMD=start`
	$(DOCKER_COMPOSE) $(CMD)
.PHONY: dc docker-compose

i: install
install: ## Install Composer dependencies
	$(COMPOSER) install
	@rm -f vendor/.lowest
	@touch vendor
.PHONY: i install

u: update
update: ## Update Composer dependencies
	$(COMPOSER) update
	@rm -f vendor/.lowest
	@touch vendor
.PHONY: u update

install-lowest: ## Install lowest Composer dependencies (libraries only)
	@if [ -f composer.lock ]; then echo 'Recipe `install-lowest` is not available in projects with `composer.lock`'; exit 1; fi
	$(COMPOSER) update --prefer-lowest --prefer-stable
	@touch vendor/.lowest
	@touch vendor
.PHONY: install-lowest

c: composer
composer: ## Run Composer command: `make c CMD=start`
	$(COMPOSER) $(CMD)
.PHONY: c composer

rescaffold:
	$(DOCKER) run \
	  --volume .:/project \
	  --user $(CONTAINER_USER) \
	  --interactive --tty --rm \
	  --pull always \
	  ghcr.io/phpyh/scaffolder:latest \
	  --user-name-default '$(shell git config user.name 2>/dev/null || whoami 2>/dev/null)' \
	  --user-email-default '$(shell git config user.email 2>/dev/null)' \
	  --package-project-default '$(shell basename $$(pwd))'
	git add --all 2>/dev/null || true
.PHONY: rescaffold

var:
	mkdir var

vendor: composer.json $(wildcard composer.lock)
	@if [ -f vendor/.lowest ]; then $(MAKE) install-lowest; else $(MAKE) install; fi

##
## Tools
## -----

fixer: var ## Fix code style using PHP-CS-Fixer
	$(RUN) php-cs-fixer fix --diff --verbose $(ARGS)
.PHONY: fixer

fixer-check: var ## Check code style using PHP-CS-Fixer
	$(RUN) php-cs-fixer fix --diff --verbose --dry-run $(ARGS)
.PHONY: fixer-check

rector: var ## Fix code style using Rector
	$(RUN) rector process $(ARGS)
.PHONY: rector

rector-check: var ## Check code style using Rector
	$(RUN) rector process --dry-run $(ARGS)
.PHONY: rector-check

phpstan: var vendor ## Analyze code using PHPStan
	$(RUN) phpstan analyze --memory-limit=1G $(ARGS)
.PHONY: phpstan

test: var vendor up ## Run tests using PHPUnit
	$(RUN) vendor/bin/phpunit $(ARGS)
.PHONY: test

infect: var vendor up ## Run mutation tests using Infection
	$(RUN) infection --show-mutations $(ARGS)
.PHONY: infect

deps-analyze: vendor ## Analyze project dependencies using Composer dependency analyser
	$(RUN) composer-dependency-analyser $(ARGS)
.PHONY: deps-analyze

composer-validate: ## Validate composer.json
	$(COMPOSER) validate $(ARGS)
.PHONY: composer-validate

composer-normalize: ## Normalize composer.json
	$(COMPOSER) normalize --no-check-lock --no-update-lock --diff $(ARGS)
.PHONY: composer-normalize

composer-normalize-check: ## Check that composer.json is normalized
	$(COMPOSER) normalize --diff --dry-run $(ARGS)
.PHONY: composer-normalize-check

fix: fixer rector composer-normalize ## Run all fixing recipes
.PHONY: fix

check: fixer-check rector-check composer-validate composer-normalize-check deps-analyze phpstan test  ## Run all project checks
.PHONY: check

# -----------------------

help:
	@awk ' \
		BEGIN {RS=""; FS="\n"} \
		function printCommand(line) { \
			split(line, command, ":.*?## "); \
        	printf "\033[32m%-28s\033[0m %s\n", command[1], command[2]; \
        } \
		/^[0-9a-zA-Z_-]+: [0-9a-zA-Z_-]+\n[0-9a-zA-Z_-]+: .*?##.*$$/ { \
			split($$1, alias, ": "); \
			sub(alias[2] ":", alias[2] " (" alias[1] "):", $$2); \
			printCommand($$2); \
			next; \
		} \
		$$1 ~ /^[0-9a-zA-Z_-]+: .*?##/ { \
			printCommand($$1); \
			next; \
		} \
		/^##(\n##.*)+$$/ { \
			gsub("## ?", "\033[33m", $$0); \
			print $$0; \
			next; \
		} \
	' $(MAKEFILE_LIST) && printf "\033[0m"
.PHONY: help

.DEFAULT_GOAL := help
