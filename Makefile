include .env
-include .env.local

SHELL ?= /bin/bash
DOCKER ?= docker
DOCKER_COMPOSE ?= $(DOCKER) compose
export CONTAINER_USER ?= $(shell id -u):$(shell id -g)
INSIDE_CONTAINER ?= $(shell test -f /.dockerenv && echo 1)

RUN ?= $(if $(INSIDE_CONTAINER),,$(DOCKER_COMPOSE) run --rm php)
COMPOSER ?= $(RUN) composer

##
## Project
## -----

var:
	mkdir var

vendor: composer.json
	if [ -f vendor/.lowest ]; then $(MAKE) install-lowest; else $(MAKE) install-highest; fi

i: install-highest
install-highest: ## Install Composer dependencies
	$(COMPOSER) update
	@rm -f vendor/.lowest
.PHONY: i install-highest

install-lowest: ## Install Composer dependencies
	$(COMPOSER) update --prefer-lowest --prefer-stable
	@touch vendor/.lowest
.PHONY: install-lowest

up: ## Docker compose up
	$(DOCKER_COMPOSE) up --build --detach $(ARGS)
.PHONY: up

down: ## Docker compose down
	$(DOCKER_COMPOSE) down --remove-orphans $(ARGS)
.PHONY: down

dc: ## Run docker compose command, e.g. `make dc CMD=start`
	$(DOCKER_COMPOSE) $(CMD)
.PHONY: start

run: ## Run a command inside the `php` Docker container, e.g. `make run CMD='php --version'`
	$(RUN) $(CMD)
.PHONY: run

t: terminal
terminal: var ## Start a terminal inside the Docker container
	@$(if $(INSIDE_CONTAINER),echo 'Already inside docker container.'; exit 1,)
	$(DOCKER_COMPOSE) run --rm php bash
.PHONY: t terminal

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
	$(COMPOSER) normalize --diff $(ARGS)
.PHONY: composer-normalize

composer-normalize-check: ## Check that composer.json is normalized
	$(COMPOSER) normalize --diff --dry-run $(ARGS)
.PHONY: composer-normalize-check

fix: fixer rector composer-normalize ## Run all fixing recipes
.PHONY: fix

check: fixer-check rector-check composer-validate composer-normalize-check phpstan test deps-analyze  ## Run all project checks
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
