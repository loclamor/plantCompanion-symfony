export COMPOSE_PROJECT_NAME=plantcompanion

build:
	docker compose -f docker-compose.yml -p $(COMPOSE_PROJECT_NAME) build

up:
	docker compose -f docker-compose.yml -p $(COMPOSE_PROJECT_NAME) up -d && \
    make -s update-host

down:
	docker compose -f docker-compose.yml -p $(COMPOSE_PROJECT_NAME) down

bash:
	docker compose -f docker-compose.yml -p $(COMPOSE_PROJECT_NAME) exec -it php bash

update-host:
	@sudo -E ./.docker/host_updater.sh plantCompanion-project $(COMPOSE_PROJECT_NAME)_default \
		$(COMPOSE_PROJECT_NAME)-nginx-1  				local.plantcompanion.fr \
		$(COMPOSE_PROJECT_NAME)-database-1  			db-locale.plantcompanion.fr

# Front (Vue 3 + Vite) — tout dans le conteneur node
front-install:
	docker compose -f docker-compose.yml -p $(COMPOSE_PROJECT_NAME) exec -it node npm install

front-dev:
	docker compose -f docker-compose.yml -p $(COMPOSE_PROJECT_NAME) exec -it node npx vite --host 0.0.0.0

front-build:
	docker compose -f docker-compose.yml -p $(COMPOSE_PROJECT_NAME) exec -it node npx vite build

front-bash:
	docker compose -f docker-compose.yml -p $(COMPOSE_PROJECT_NAME) exec -it node sh

# Test commands
test:
	docker compose -f docker-compose.yml -p $(COMPOSE_PROJECT_NAME) exec -it php bin/phpunit

test-api:
	docker compose -f docker-compose.yml -p $(COMPOSE_PROJECT_NAME) exec -it php bin/phpunit tests/Controller/Api/

# Shortcut to run all tests
tests:
	docker compose -f docker-compose.yml -p $(COMPOSE_PROJECT_NAME) exec -it php bin/phpunit --testdox
