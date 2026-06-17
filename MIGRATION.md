# Migration plantCompanion (Symfony 6.4) — suivi

Migration du projet legacy `/home/loic/perso/plant-companion` (PHP natif, framework BPC) vers Symfony 6.4.
**Base de données partagée** avec le legacy → schéma rétrocompatible obligatoire, pas de migration destructive.

**Versions** : Symfony **7.4 LTS**, PHP 8.3, Doctrine ORM 3 / DBAL 3 (mise à jour faite — voir « Mise à jour deps » ci-dessous).

Stratégie validée : **fondations d'abord**, puis montée en complexité.
- Photos : `VichUploaderBundle` (upload) + `LiipImagineBundle` (thumbnails).
- Scoping multi-utilisateur : fondation prioritaire.

Patron de référence : `VegetableController` / `VegetableType` / `templates/vegetable/`.

---

## Mise à jour deps ✅ (Symfony 6.4 → 7.4 LTS)

- [x] composer.json : contraintes `6.4.*` → `7.4.*` (+ `extra.symfony.require`) ; Doctrine inchangé (DBAL 3 / ORM 3)
- [x] `composer update -W` → Symfony 7.4.13, `cache:clear` OK
- [x] `OwnerVoter::voteOnAttribute()` : ajout 4e arg `?Vote $vote = null` (signature 7.4)
- [x] routes dev : imports `errors.xml`/`wdt.xml`/`profiler.xml` → `.php`
- [x] recipes 7.4 ajoutées : `config/packages/csrf.yaml` (CSRF stateless), `property_info.yaml`, `config/reference.php`
- [x] `bin/console debug:container --deprecations` : 0 déprécation ; tests inchangés
- Note : conteneur a composer 2.2.25 (image cache) ; `make build` (composer:lts) corrigera. PHP reste 8.3 (OK pour SF 7.4).

---

## Phase 0 — Fondations ✅ (terminée, branche `feat/migration-phase0-fondations`)

- [x] `__toString` sur `Type`, `Group`, `Lieu`, `PorteGreffe`, `Vegetable`, `Photo`
- [x] Interface `App\Entity\UserOwnedInterface` (implémentée par les 8 entités possédées)
- [x] `App\Security\Voter\OwnerVoter` générique (`OWNER_VIEW` / `OWNER_EDIT` / `OWNER_DELETE`)
- [x] `VegetableRepository::findByUser()`
- [x] `VegetableController` : index scopé, `new()` fixe `utilisateur` + dates auto, Voter sur show/edit/delete
- [x] `VegetableType` : `EntityType` filtrés par user (`query_builder`), `choice_label: 'name'`, retrait `utilisateur`/`defaultPhoto`
- [x] Tests Vegetable verts (3/3) ; lint OK

Note env : `composer install` + dev `symfony/browser-kit`, `symfony/css-selector` ajoutés (sinon les tests ne bootaient pas — `TwigBundle not found`).

---

## Phase 1 — CRUDs simples (réplique patron Vegetable durci) ✅

Pour `Type`, `Group`, `Lieu`, `PorteGreffe` (écrits à la main sur le patron Vegetable) :
- [x] scoping `findByUser` dans chaque repository
- [x] `OwnerVoter` sur show/edit/delete
- [x] `utilisateur` auto-rempli (hors formulaire)
- [x] `choice_label: 'name'` + `EntityType` filtrés par user
- [x] hiérarchies `Type.parent`, `Group.parent` filtrées (exclure soi-même en édition)
- [x] `PorteGreffe.type` (ManyToOne) filtré
- [x] liens navbar « Paramétrage » dans `templates/base.html.twig`
- [x] tests contrôleur (auth requise) : `tests/Controller/ParametrageControllerTest.php` (data provider, 12 cas)
- [x] vérifié : 20 routes enregistrées, lint PHP + Twig OK, suite 19/20 verte (seul échec = login pré-existant)

## Phase 2 — Action (interventions) + historique ✅

- [x] CRUD `Action` : sélection `Vegetable` (filtrée user), `typeAction` (ChoiceType), `date`, `title`, `comment` ; scoping + OwnerVoter + CSRF
- [x] constantes `Action::TYPES_ACTION` et `Action::TITRES_OBSERVATION`
- [x] `ActionRepository::findByUser()` + `findByVegetable()`
- [x] listener Doctrine `VegetableHistoryListener` (`preUpdate` collecte le changeset, `postFlush` persiste un `VegetableHistory` par champ ; formatage null/date/bool/objet ; garde anti-réentrance)
- [x] affichage interventions + historique dans `vegetable/show.html.twig` (`VegetableHistoryRepository::findByVegetable`)
- [x] navbar : lien « Interventions »
- [x] tests `ActionControllerTest` (auth requise)
- [x] vérifié : 5 routes Action, listener taggé preUpdate+postFlush, lint PHP+Twig OK, suite 22/23 verte
- À noter (reporté) : `title` est un champ libre ; le dropdown conditionnel « si observation » nécessite du JS (sans bundler) → polish ultérieur. Vérification end-to-end de l'historique (création user + édition) à couvrir en Phase 5 avec fixtures.

## Phase 3 — Photos (VichUploaderBundle + LiipImagineBundle) ✅

- [x] `composer require vich/uploader-bundle liip/imagine-bundle` (+ `symfony/asset`, requis par le thème de formulaire Vich) ; bundles enregistrés à la main dans `config/bundles.php` (recipes contrib ignorées : `allow-contrib: false`)
- [x] Stockage : Vich écrit dans `./uploads` (racine, partagé legacy), exposé via symlink `public/uploads -> ../uploads` ; `config/packages/vich_uploader.yaml` (mapping `photo`, `SmartUniqueNamer`, `delete_on_remove`)
- [x] `Photo` : `#[Vich\Uploadable]` + `imageFile` (non persisté, `fileNameProperty: path`) + `getRelativePath()` (normalise legacy `./uploads/...` et nouveaux fichiers → `uploads/...`). **Aucune migration** (champ Vich non mappé en BD, colonne `path` conservée)
- [x] `config/packages/liip_imagine.yaml` (driver gd, filtres `plant_thumb`/`plant_large`, `twig.mode: lazy`) + import routing `config/routes/liip_imagine.yaml`
- [x] `PhotoController` : upload simple (`/photo/new`, pré-sélection `?vegetable=`), upload multiple (`/photo/upload-multiple`), suppression (Vich `delete_on_remove` + purge cache Liip + dénoue defaultPhoto), photo par défaut (`/photo/{id}/default`)
- [x] `PhotoType` (VichImageType, vegetable/action filtrés) ; `PhotoRepository::findByVegetable()`/`findByUser()`
- [x] EXIF serveur optionnel : `ExifDateExtractor` (gardé si extension absente), flash de la date détectée ; `exif` ajouté au Dockerfile (rebuild requis)
- [x] Affichage : galerie + boutons (défaut/supprimer) dans `vegetable/show.html.twig`, vignette par défaut dans `vegetable/index.html.twig`. Thumbnails Liip via `path('liip_imagine_filter', …)` (la fonction Twig `imagine_filter` n'est pas exploitable dans cette version → route + `path()`, lint-clean + runtime garanti)
- [x] tests `PhotoControllerTest` ; correction dépréciation `Length` (arguments nommés) dans `RegistrationFormType`
- [x] vérifié : lint PHP + Twig (41 fichiers) OK, génération d'URL Liip OK, suite 24/25 verte (seul échec = login pré-existant)
- À noter (reporté) : upload JS avancé (drag-drop/preview) non repris ; vérification end-to-end de l'upload réel (fichier + thumbnail) → Phase 5 (fixtures + utilisateur authentifié)

## Phase 4 — Features avancées (parité legacy)

- [ ] sélecteur de groupe courant en navbar (session) + filtrage plantes/actions
- [ ] liste plantes : recherche nom, filtre type, tri (création/ajout/nom/rusticité), pagination, filtres persistés en session (`knp-paginator-bundle` ou maison)
- [ ] `CalendarController` fructification (floraison vert / récolte orange + micro-grille observations)
- [ ] `PrintController::bytype` (cartes A4, CSS `@media print`)

## Phase 5 — Tests & finition

- [ ] tests contrôleur avec utilisateur authentifié (fixtures / `loginUser()`) — corrige `testLoginWithValidCredentials` (échec connu)
- [ ] tests de scoping (un user ne voit pas les données d'un autre)
- [ ] tests CRUD + historique
- [ ] cibles `make test-action`, `make test-type`, etc.

---

## Précaution transverse — migrations Doctrine

Dossier `migrations/` vide (schéma géré par le legacy). Avant toute migration : `bin/console doctrine:migrations:diff` puis **relire le diff** — rien de destructif pour le legacy (BD partagée). Nouvelles colonnes (ex. Vich) → nullable / rétrocompatibles. Jamais renommer/supprimer table ou colonne utilisée par l'ancien projet.

## Références

- Plan détaillé : `~/.claude/plans/voit-l-tat-actuel-du-enchanted-tide.md`
- Legacy (source de vérité fonctionnelle) : `/home/loic/perso/plant-companion/src/{controller,model,view}/`
- Ignorer : `src/Entity/Utilisateur_old.php`, `src/Repository/UtilisateurRepository_old.php`
