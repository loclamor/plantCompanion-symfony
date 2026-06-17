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

# Test commands
test:
	docker compose -f docker-compose.yml -p $(COMPOSE_PROJECT_NAME) exec -it php bin/phpunit

test-security:
	docker compose -f docker-compose.yml -p $(COMPOSE_PROJECT_NAME) exec -it php bin/phpunit tests/Controller/SecurityControllerTest.php

test-vegetable:
	docker compose -f docker-compose.yml -p $(COMPOSE_PROJECT_NAME) exec -it php bin/phpunit tests/Controller/VegetableControllerTest.php

# Shortcut to run all tests
tests:
	docker compose -f docker-compose.yml -p $(COMPOSE_PROJECT_NAME) exec -it php bin/phpunit --testdox
