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

## Phase 2 — Action (interventions) + historique

- [ ] CRUD `Action` : sélection `Vegetable` (filtrée user), `typeAction` (ChoiceType : observation/ajout_engrais/taille/rempotage/ceuillette), `title` liste si observation (Fleurs/Fruits/Maladie/Auxiliaire/Ravageur/nouvelle_feuille) sinon libre, `date`, `comment`
- [ ] constantes de valeurs sur l'entité `Action`
- [ ] EventSubscriber Doctrine (`preUpdate`/`onFlush`) → `VegetableHistory` par champ modifié
- [ ] affichage historique dans `vegetable/show.html.twig`

## Phase 3 — Photos (VichUploaderBundle + LiipImagineBundle)

- [ ] `composer require vich/uploader-bundle liip/imagine-bundle` + config (répertoire compatible `Photo.path` legacy, filtres = tailles minH/minW)
- [ ] `Photo` Vich (`#[Vich\Uploadable]`, champ fichier non mappé, conserver `path`)
- [ ] `PhotoController` : upload simple/multiple, liaison Vegetable/Action, suppression (fichier + thumbnails), photo par défaut (`Vegetable.defaultPhoto`)
- [ ] EXIF : date de prise → pré-remplir date action (confort, en fin de phase)
- [ ] affichage : carousel `show`, miniature + badge nb photos `index`, thumbnails LiipImagine
- Note : pas de bundler JS → upload serveur classique d'abord, JS avancé ensuite

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
