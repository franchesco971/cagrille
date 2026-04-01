.PHONY: run init-cagrille php-shell stan

DOCKER_COMPOSE ?= docker compose
# Avoid using root (0:0) as DOCKER_USER, especially in WSL2 environments
# If id -u returns 0, fall back to 1000:1000 to prevent fixuid/PHP-FPM issues
DOCKER_USER ?= $(shell if [ "$$(id -u)" = "0" ]; then echo "1000:1000"; else echo "$$(id -u):$$(id -g)"; fi)
ENV ?= "dev"

init:
	@make -s docker-compose-check
	@if [ "$$(id -u)" = "0" ]; then \
		echo "Running as root (WSL2/Linux) - preparing permissions for Docker containers..."; \
		chmod -R 777 var/ public/ 2>/dev/null || true; \
		chown -R 1000:1000 vendor/ node_modules/ 2>/dev/null || true; \
	fi
	@if [ ! -e compose.override.yml ]; then \
		cp compose.override.dist.yml compose.override.yml; \
	fi
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run --rm php composer install --no-interaction --no-scripts
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run --rm nodejs
	@make -s install
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) up -d

run:
	@make -s up

debug:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) -f compose.yml -f compose.override.yml -f compose.debug.yml up -d

up:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) up -d

down:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) down

install:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run --rm php bin/console sylius:install -s default -n

install-no-fixtures: ## Installer Sylius sans charger les fixtures (migrations uniquement)
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run --rm php bin/console sylius:install -s database -n
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) up -d

clean:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) down -v

php-shell: ## Ouvrir un shell interactif dans le container PHP
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) exec php sh

init-cagrille: ## Initialiser Sylius from scratch dans le container PHP (APP_ENV=dev par défaut)
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) exec -e APP_ENV=$(ENV) php sh bin/init-cagrille

node-shell:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run --rm -i nodejs sh

node-watch:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run --rm -i nodejs "npm run watch"

stan: ## Lancer PHPStan (niveau 9) sur tout le projet
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) exec php vendor/bin/phpstan analyse --memory-limit=512M

docker-compose-check:
	@$(DOCKER_COMPOSE) version >/dev/null 2>&1 || (echo "Please install docker compose binary or set DOCKER_COMPOSE=\"docker-compose\" for legacy binary" && exit 1)
	@echo "You are using \"$(DOCKER_COMPOSE)\" binary"
	@echo "Current version is \"$$($(DOCKER_COMPOSE) version)\""
