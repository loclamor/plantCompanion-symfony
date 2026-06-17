# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

plantCompanion : application Symfony 7.4 / PHP 8.3 de gestion de potager. Un `Utilisateur` enregistre des `Vegetable` (plantes) rattachés à un `Type`, un `Group`, un `Lieu`, un `PorteGreffe`, des `Photo` et des `Action` (interventions). `VegetableHistory` trace les modifications. Doctrine ORM 3, formulaires Symfony + thème Bootstrap 5, Twig. Pas de bundler JS (pas d'Encore/AssetMapper).

## Migration depuis l'ancien projet — base partagée

Ce projet est la migration (vers Symfony 7.4) du projet initial situé dans `/home/loic/perso/plant-companion`. **La base de données est partagée avec l'ancien projet et doit, pour le moment, rester utilisable par lui.**

Conséquences contraignantes :
- Ne pas casser le schéma existant : pas de renommage/suppression de tables ou colonnes utilisées par l'ancien code, pas de migration destructive. Les changements de schéma doivent rester rétrocompatibles.
- C'est la raison d'être du préfixe de table dynamique (voir plus bas) : les noms de tables doivent correspondre à ceux de l'ancien projet.
- Avant toute migration Doctrine, vérifier qu'elle n'altère pas une structure dont l'ancien projet dépend.

## Environnement de dev — tout passe par Docker

Le dev tourne dans des conteneurs (php-fpm, nginx sur `:8001`, MySQL). On n'exécute PAS php/composer/bin sur l'hôte. Cibles `make` (voir `Makefile`) :

- `make build` — build des images
- `make up` — démarre les conteneurs (+ met à jour /etc/hosts via `update-host`, requiert sudo)
- `make down` — arrête
- `make bash` — shell interactif dans le conteneur `php` (pour lancer `bin/console`, `composer`, migrations…)

Pour toute commande Symfony, entrer dans le conteneur : `make bash` puis `bin/console ...` (ex. `bin/console make:entity`, `bin/console doctrine:migrations:migrate`, `bin/console doctrine:migrations:diff`).

Note : `.env` contient un `DATABASE_URL` PostgreSQL (placeholder par défaut de Symfony) mais le conteneur réel est **MySQL** (`docker-compose.yml`). Le `DATABASE_URL` effectif est fourni à l'environnement Docker.

## Tests

PHPUnit, exécuté dans le conteneur `php` (`phpunit.xml.dist`, `APP_ENV=test`). Tests sous `tests/`.

- `make test` — toute la suite
- `make tests` — toute la suite en `--testdox`
- `make test-security` — `tests/Controller/SecurityControllerTest.php`
- `make test-vegetable` — `tests/Controller/VegetableControllerTest.php`
- Un seul test à la main : `make bash` puis `bin/phpunit --filter testNom tests/Controller/XxxTest.php`

## Architecture / pièges

- **Auth par `name`, pas par email.** Le user provider charge `App\Entity\Utilisateur` via la propriété `name` (`config/packages/security.yaml`). Login par formulaire (`/login`), CSRF activé, remember_me 1 semaine. Accès : `/login` et `/register` publics, tout le reste exige `ROLE_USER`.

- **Préfixe de table dynamique.** `src/EventListener/TablePrefixEventListener.php` (hook `loadClassMetadata`) préfixe les noms de tables selon `config/packages/table_prefix.yaml` (mapping nom de namespace → préfixe), câblé dans `config/services.yaml` (service `kernel.listener.prefix`). Les `@ORM\Table` ne reflètent donc pas le nom réel en base.

- **CRUD générés par maker-bundle.** `VegetableController` est le patron de référence (index/new/show/edit/delete, ParamConverter sur `{id}`, CSRF sur delete, `_form.html.twig` partagé). Reproduire ce patron pour de nouvelles entités plutôt que réinventer.

- **Multi-utilisateur.** Quasiment toutes les entités ont un ManyToOne vers `Utilisateur` (propriétaire). En tenir compte lors des requêtes/créations.

- **Fichiers legacy à ignorer / ne pas réutiliser :** `src/Entity/Utilisateur_old.php` et `src/Repository/UtilisateurRepository_old.php`. L'entité active est `Utilisateur` (sans suffixe).

- **Entités auto-référencées :** `Vegetable.parent`, `Type.parent`, `Group.parent` (hiérarchies). Table `Group` est un mot réservé SQL → nom quoté.

## Structure

- `src/Entity/`, `src/Repository/` — modèle Doctrine (attributs)
- `src/Controller/` — `VegetableController`, `SecurityController`, `RegistrationController`
- `src/Form/` — `VegetableType`, `RegistrationFormType`
- `templates/` — Twig (`base.html.twig`, `vegetable/`, `security/`, `registration/`)
- `migrations/` — migrations Doctrine
- `config/packages/` — `security.yaml`, `doctrine.yaml`, `table_prefix.yaml`, `twig.yaml`
- `.docker/` — Dockerfiles php/nginx, `host_updater.sh`
