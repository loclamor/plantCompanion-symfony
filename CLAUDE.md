# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

plantCompanion : application Symfony 7.4 / PHP 8.3 de gestion de potager. Un `Utilisateur` enregistre des `Vegetable` (plantes) rattachés à un `Type`, un `Group`, un `Lieu`, un `PorteGreffe`, des `Photo` et des `Action` (interventions). `VegetableHistory` trace les modifications. Doctrine ORM 3.

**Front = SPA Vue 3** (sous `assets/`, build Vite via `pentatrion/vite-bundle`) servi à la racine `/`, consommant une **API JSON** Symfony sous `/api`. Bootstrap 5 + thème vert. Les pages Twig CRUD ont été décommissionnées ; seule subsiste l'inscription `/register` (Twig). Voir « Front / API » plus bas.

## Environnement de dev — tout passe par Docker

Le dev tourne dans des conteneurs (php-fpm, nginx sur `:8001`, MySQL). On n'exécute PAS php/composer/bin sur l'hôte. Cibles `make` (voir `Makefile`) :

- `make build` — build des images
- `make up` — démarre les conteneurs (+ met à jour /etc/hosts via `update-host`, requiert sudo)
- `make down` — arrête
- `make bash` — shell interactif dans le conteneur `php` (pour lancer `bin/console`, `composer`, migrations…)

Pour toute commande Symfony, entrer dans le conteneur : `make bash` puis `bin/console ...` (ex. `bin/console make:entity`, `bin/console doctrine:migrations:migrate`, `bin/console doctrine:migrations:diff`).

Note : `.env` contient un `DATABASE_URL` PostgreSQL (placeholder par défaut de Symfony) mais le conteneur réel est **MySQL** (`docker-compose.yml`). Le `DATABASE_URL` effectif est dans `.env.local` (non versionné).

**Charset connexion = `utf8mb4` (impératif).** Les colonnes sont en utf8mb4 et contiennent du UTF-8 valide (la migration de réparation `Version20260618130000` a été appliquée). Un `charset=latin1` sur la connexion fait reconvertir en latin1 côté client → octets invalides → 500 « Malformed UTF-8 ». Garder `&charset=utf8mb4` dans `DATABASE_URL`.

## Tests

PHPUnit, exécuté dans le conteneur `php` (`phpunit.xml.dist`, `APP_ENV=test`). Tests sous `tests/`.

- `make test` — toute la suite
- `make tests` — toute la suite en `--testdox`
- `make test-api` — `tests/Controller/Api/` (tests fonctionnels de l'API)
- Un seul test à la main : `make bash` puis `bin/phpunit --filter testNom tests/Controller/Api/XxxTest.php`

Les tests sont des tests fonctionnels de l'API JSON (`tests/Controller/Api/`, base SQLite de test recréée par `DatabaseTestCase`). Frontend Vue : build via `make front-build`.

## Architecture / pièges

- **Auth par `name`, pas par email.** Le user provider charge `App\Entity\Utilisateur` via la propriété `name` (`config/packages/security.yaml`). **Auth gérée par le SPA** : connexion `POST /api/login` (`Security::login`), déconnexion `POST /api/logout` (`Security::logout`), `GET /api/me`. Pas de `form_login`. `AppEntryPoint` : 401 JSON pour `/api/*` non authentifié, sinon redirige vers `/login` (route SPA). `access_control` : `/api/login` et `/register` publics, `/api` exige `ROLE_USER`, le reste (coquille SPA) est public — c'est l'API qui garde l'accès. Session/cookie, pas de JWT.

- **Préfixe de table dynamique.** `src/EventListener/TablePrefixEventListener.php` (hook `loadClassMetadata`) préfixe les noms de tables selon `config/packages/table_prefix.yaml` (mapping nom de namespace → préfixe), câblé dans `config/services.yaml` (service `kernel.listener.prefix`). Les `@ORM\Table` ne reflètent donc pas le nom réel en base. Toujours requis : les tables réelles sont nommées `plant_*` et l'application en dépend (ce n'est pas une survivance legacy).

- **API JSON (patron de référence).** `src/Controller/Api/VegetableApiController.php` est le patron CRUD (index filtré/paginé, show/create/update/delete, `OwnerVoter` pour l'accès, sérialisation manuelle en tableaux passés par `App\Service\Utf8::clean`). Les entités de paramétrage utilisent la base générique `AbstractOwnedCrudApiController`. Reproduire ces patrons pour de nouvelles entités. `SpaController` sert la coquille (`spa.html.twig`) en catch-all `/{vue}` (hors `/api`, `/register`, internes).

- **Résilience UTF-8.** La base a été migrée en utf8mb4 propre. Tout payload API texte passe quand même par `App\Service\Utf8::clean` : conservé comme filet défensif (coût négligeable) au cas où une ligne aurait échappé à la migration ou pour un import futur mal encodé. N'est plus vital.

- **Multi-utilisateur.** Quasiment toutes les entités ont un ManyToOne vers `Utilisateur` (propriétaire). En tenir compte lors des requêtes/créations.

- **Entités auto-référencées :** `Vegetable.parent`, `Type.parent`, `Group.parent` (hiérarchies). Table `Group` est un mot réservé SQL → nom quoté.

## Front / API

- **Backend API** : `src/Controller/Api/` (Vegetable, Action, Photo, Calendar, Print, Profile, Auth + CRUD paramétrage Type/Group/Lieu/PorteGreffe). `src/Controller/SpaController.php` sert la coquille. `RegistrationController` = seule page Twig restante (`/register`).
- **Front Vue** : `assets/` — `src/main.js`, `src/App.vue` (navbar), `src/router/`, `src/stores/` (Pinia : auth, group), `src/api/http.js` (axios, intercepteur 401 → `/login`), `src/views/`, `src/components/`, `src/css/theme.css` (thème vert). Build : `make front-install` / `make front-dev` (HMR) / `make front-build` → `public/build/` (lu par pentatrion/vite-bundle). Conteneur `node` dans `docker-compose.yml`.

## Structure

- `src/Entity/`, `src/Repository/` — modèle Doctrine (attributs)
- `src/Controller/Api/` — contrôleurs API JSON ; `src/Controller/{SpaController,RegistrationController}.php`
- `src/Service/` — `CurrentGroup`, `PhotoPresenter`, `Utf8`, `ExifDateExtractor`
- `templates/` — `spa.html.twig` (coquille SPA) + `registration/`
- `migrations/` — migrations Doctrine
- `config/packages/` — `security.yaml`, `doctrine.yaml`, `table_prefix.yaml`, `pentatrion_vite.yaml`, `liip_imagine.yaml`, `vich_uploader.yaml`
- `.docker/` — Dockerfiles php/nginx (php : gd `--with-jpeg`, `uploads.ini`), `host_updater.sh`
