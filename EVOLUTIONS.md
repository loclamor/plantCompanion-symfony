# Évolutions — Module « Potager »

> Plan de construction d'une nouvelle partie de PlantCompanion dédiée à la gestion d'un
> potager annualisé. Découpé en phases séquentielles, chacune livrable indépendamment,
> à réaliser au fur et à mesure du temps.

## Contexte

PlantCompanion gère aujourd'hui des plantes pérennes/ornementales (`Vegetable` :
porte-greffe, rusticité, nom latin, mois de floraison/fructification) via une SPA Vue 3
+ API JSON Symfony. L'objectif est d'ajouter la gestion d'**un potager**, paradigme
différent :

- **Annualisé** : on démarre une **nouvelle saison** qui remet à zéro la majorité des
  plantes, sauf les **cultures pérennes** (fraisier, framboisier, asperges…) reportées.
- **Bacs** de tailles variables, réutilisés d'une année sur l'autre, mais
  ajoutables/modifiables/supprimables — **sans impacter les saisons passées**, qui
  restent **visualisables en lecture seule**.
- **Vue plan** du potager : voir le contenu des bacs et l'**emplacement exact** des
  plants (grille de cases type *square-foot gardening*).
- **Semis** suivis de la graine jusqu'au plant mis en bac.
- **Grainothèque** : toutes les graines par type (code unique réutilisable : `CGT1`,
  `T1`, `TC1`…), avec plusieurs **lots** (date d'achat/récolte + quantité restante par
  lot), dates théoriques de semis/plantation/récolte, méthode (direct / godet intérieur).
- Un plant en bac n'est **pas forcément** issu d'un semis : ajout possible à tout moment.

**Décisions de cadrage :**
1. **Module séparé** : nouvelles entités, distinctes de `Vegetable`. Le potager vit dans
   son propre espace ; `Vegetable` reste inchangé.
2. **Placement = grille de cases.** La **taille physique d'un bac** (dimensions en cm +
   position dans le plan) est **figée pour la saison**, mais son **découpage en
   lignes/colonnes** peut évoluer en cours de saison.
3. **Nouvelle saison** reporte : géométrie des bacs + cultures pérennes. La grainothèque
   persiste (hors saison). Le reste (semis, cultures annuelles) repart de zéro.

Résultat attendu : un onglet « Potager » avec sélecteur de saison, gestion de
grainothèque/semis/bacs/cultures, et une vue plan interactive (lecture seule sur les
saisons clôturées).

---

## Architecture cible (patrons existants réutilisés)

- **API** : routes par attributs `#[Route('/api/...')]`. CRUD simples → étendre
  `src/Controller/Api/AbstractOwnedCrudApiController.php` (méthodes `do*`, `applyName`,
  `resolveOwned`). CRUD riche (filtre/pagination/sérialisation imbriquée) → calquer
  `src/Controller/Api/VegetableApiController.php`.
- **Accès** : toutes les entités implémentent `App\Entity\UserOwnedInterface`
  (`getUtilisateur()`), contrôle via `App\Security\Voter\OwnerVoter` (VIEW/EDIT/DELETE).
- **Propriétaire** : `setUtilisateur($user)` à la création (cf. `#[CurrentUser]`).
- **Sérialisation** : tout payload texte passe par `App\Service\Utf8::clean`.
- **Préfixe table** : entités sous `App\Entity\` → tables `plant_*` automatiquement
  (`TablePrefixEventListener`). Rien à configurer.
- **Sélecteur de saison** : calquer `App\Service\CurrentGroup` → nouveau
  `App\Service\CurrentSeason` (clé session `current_season`), exposé comme
  `/api/current-season` (cf. `AuthController` pour `/api/current-group`).
- **Front** : nouvelles routes dans `assets/src/router/index.js`, entrée de menu
  « Potager » dans `assets/src/App.vue`, store Pinia `season` calqué sur
  `assets/src/stores/group.js`, requêtes via `assets/src/api/http.js`. Listes/formulaires
  génériques : s'inspirer de `ParametrageList.vue` / `ParametrageForm.vue`.
- **Migrations** : dans le conteneur (`make bash`) → `bin/console make:entity` puis
  `bin/console doctrine:migrations:diff` et `doctrine:migrations:migrate`.
- **Tests** : tests fonctionnels API sous `tests/Controller/Api/` (base SQLite,
  `DatabaseTestCase`). `make test-api`.

---

## Modèle de données (nouvelles entités, toutes `UserOwnedInterface`)

- **Saison** — `name`, `annee` (int), `dateDebut`, `dateFin?`, `statut`
  (`active` | `cloturee`), `utilisateur`. Une seule `active` par utilisateur.
- **GraineType** (type de graine *générique*) — `name` (ex. « Tomate Cerise »),
  `code` (préfixe unique par utilisateur, ex. `TC`), `utilisateur`. Sert à classer les
  graines et à générer leurs codes. **Persistant, hors saison.**
- **Graine** (graine *concrète*) — `graineType` → GraineType, `code` (unique par
  utilisateur, ex. `TC1` ; pré-rempli par incrément auto depuis le préfixe, éditable),
  `name` (ex. « Sweet »), `methodeSemisConseillee?` (`pleine_terre` | `couvert`, indicatif),
  `moisSemis?`, `moisPlantationTheorique?`, `moisRecolteTheorique?`, `notes?`, `utilisateur`.
  La méthode de semis *effective* sera portée par le plant (Phase 5 :
  couvert / pleine terre / achat). **Persistant, hors saison.**
- **GraineLot** — `graine` → Graine, `source` (`achat` | `recolte`),
  `dateAcquisition`, `quantiteInitiale`, `quantiteRestante`, `fournisseur?`, `notes?`,
  `utilisateur`. Plusieurs lots par Graine (quantité liée à la date).
- **Semis** — `saison` → Saison, `graineType` → GraineType, `graineLot?` → GraineLot,
  `dateSemis`, `methode` (`direct`|`godet`), `quantiteSemee`, `datePlantationTheorique?`,
  `dateRecolteTheorique?`, `statut` (`seme` | `leve` | `repique` | `plante` | `echec`),
  `notes?`, `utilisateur`. **Scopé saison.** Consommer le lot (décrémenter
  `quantiteRestante`) à la création.
- **Bac** — identité logique persistante du bac : `name`, `largeurDefaut`,
  `longueurDefaut` (cm), `lignesDefaut`, `colonnesDefaut`, `archived` (bool),
  `utilisateur`. Réutilisé d'une saison à l'autre.
- **BacSaison** — **snapshot par saison** : `bac` → Bac, `saison` → Saison.
  - **Taille physique figée pour la saison** : `largeur`, `longueur` (cm), `posX`, `posY`
    (position dans le plan du potager). Définie au démarrage de la saison ; **immuable en
    cours de saison**.
  - **Découpage modifiable en cours de saison** : `lignes`, `colonnes` (subdivision de la
    grille de cases). Éditable tant que la saison est `active`.
  - `utilisateur`. Modifier/supprimer un `Bac` n'altère pas les `BacSaison` des saisons
    passées → immutabilité du passé.
- **Culture** — un plant placé dans un bac sur une saison : `saison` → Saison,
  `bacSaison` → BacSaison, `graineType?` → GraineType, `semis?` → Semis, `name`,
  `posX`, `posY`, `largeurCases` (défaut 1), `hauteurCases` (défaut 1), `datePlantation`,
  `dateRecolteTheorique?`, `dateFin?`, `statut` (`en_place` | `recolte` | `mort`),
  `perenne` (bool), `parentCulture?` → Culture (self-ref, lignage de report
  inter-saisons), `utilisateur`.

**Règles transverses :**
- **Lecture seule du passé** : toute écriture (POST/PUT/DELETE) sur Semis/BacSaison/Culture
  d'une saison `cloturee` est refusée (HTTP 409 + message). Helper de garde commun
  (ex. dans `CurrentSeason` ou un service `SeasonGuard`).
- **Taille physique vs découpage** : `largeur`/`longueur`/`posX`/`posY` d'un `BacSaison`
  sont figés une fois la saison démarrée ; seuls `lignes`/`colonnes` restent modifiables
  tant que la saison est `active`.
- **Validation de placement** : `posX/posY + emprise` d'une `Culture` doivent tenir dans
  `BacSaison.lignes × colonnes` ; pas de chevauchement entre cultures `en_place` du même
  bac. Réduire le découpage (lignes/colonnes) avec des cultures hors limites → refus (409)
  avec la liste des cultures en conflit.
- **Code grainothèque unique par utilisateur** : valider à la création/édition (422 sinon).

---

## Découpage en phases (séquentielles)

### Phase 1 — Grainothèque (autonome, sans dépendance saison) ✅ FAIT
- Entités `GraineType` (générique) + `Graine` (concrète) + `GraineLot` (+ migration
  `Version20260623064924`).
- API : `GraineTypeApiController` (`/api/graine-types`, unicité préfixe) et
  `GraineApiController` (`/api/graines`, génération auto du code via préfixe +
  `GET /api/graines/next-code?graineType=ID`, validation unicité code, filtre
  `?graineType=`) étendent `AbstractOwnedCrudApiController`. `GraineLotApiController`
  (`/api/graine-lots`, lots rattachés à une `graine` ; liste filtrée `?graine=`, quantités).
- Front : vues `GraineList`/`GraineForm`/`GraineDetail` (graines) + `GraineTypeList`/`GraineTypeForm`
  (types). Création inline d'un type depuis le formulaire graine. Lots gérés inline dans
  le détail (ajout/édition/suppression, stock restant, indice « à racheter » si
  `quantiteRestante == 0`). Routes `/potager/grainotheque` + `/potager/types-graines` ;
  menu « Potager › Grainothèque / Types de graines ».
- Tests API : CRUD + unicité (préfixe & code) + génération code + filtres + stock.
- **Fichiers** : `src/Entity/{GraineType,Graine,GraineLot}.php`,
  `src/Repository/{GraineTypeRepository,GraineRepository,GraineLotRepository}.php`,
  `src/Controller/Api/{GraineTypeApiController,GraineApiController,GraineLotApiController}.php`,
  `assets/src/views/graine/*`, `assets/src/router/index.js`, `assets/src/App.vue`,
  `tests/Controller/Api/{GraineTypeApiTest,GraineApiTest,GraineLotApiTest}.php`.

### Phase 2 — Saison (colonne vertébrale) ✅ FAIT
- Entité `Saison` (`name`, `annee`, `dateDebut`, `dateFin?`, `statut` active|cloturee,
  `utilisateur`) + migration `Version20260623094850` (table `plant_saison`).
- Service `App\Service\CurrentSeason` (session `current_season`, calqué `CurrentGroup`,
  fallback sur la saison active) + endpoints `GET/PUT /api/current-season` (dans
  `AuthController`) et CRUD `Saison` (`SaisonApiController`, étend
  `AbstractOwnedCrudApiController`). Action `PUT /api/saisons/{id}/cloturer`.
  **POST auto-clôture la saison active précédente** (une seule active/utilisateur).
- Garde « saison clôturée = lecture seule » : `App\Service\SeasonGuard`
  (`isWritable`/`assertWritable` → `App\Exception\ClosedSeasonException`) **livré seul,
  non branché** (consommateurs Semis/Cultures/Bacs en Phases 3-5).
- Front : store Pinia `season`, **sélecteur de saison dans la navbar** (visible en
  contexte Potager), entrée de menu « Saisons », vues liste/formulaire des saisons,
  bouton « Nouvelle saison » (squelette : la logique de report arrive en Phase 4).
- Tests : `SaisonApiTest` (CRUD, une seule active, clôture, sélection courante +
  fallback, isolation propriétaire, 422) + `SeasonGuardTest` (unitaire).
- **Fichiers** : `src/Entity/Saison.php`, `src/Repository/SaisonRepository.php`,
  `src/Service/CurrentSeason.php`, `src/Service/SeasonGuard.php`,
  `src/Exception/ClosedSeasonException.php`, `src/Controller/Api/SaisonApiController.php`,
  endpoints saison dans `AuthController`, `assets/src/stores/season.js`,
  `assets/src/App.vue`, `assets/src/router/index.js`, `assets/src/views/saison/*`,
  `tests/Controller/Api/SaisonApiTest.php`, `tests/Service/SeasonGuardTest.php`.

### Phase 3 — Semis (scopé saison, consomme la grainothèque)
- Entité `Semis` (+ migration).
- API `SemisApiController` (calquer `VegetableApiController` : liste filtrée par saison +
  statut, CRUD, décrément du `GraineLot`). Écriture refusée si saison clôturée.
- Front : vues liste/formulaire semis, filtres (statut, graineType), affichage du cycle
  (semé → levé → repiqué → planté/échec), sélection du lot consommé.
- Tests API : CRUD, décrément lot, blocage saison clôturée.
- **Fichiers** : `src/Entity/Semis.php`, `src/Repository/SemisRepository.php`,
  `src/Controller/Api/SemisApiController.php`, `assets/src/views/semis/*`,
  `tests/Controller/Api/SemisApiTest.php`.

### Phase 4 — Bacs + cycle de saison (report géométrie + pérennes)
- Entités `Bac` + `BacSaison` (+ migration).
- API : `BacApiController` (identité logique, archivage), `BacSaisonApiController`
  (taille physique figée à la saison ; lignes/colonnes éditables si saison active).
- Service `SaisonCycleService.startNewSeason(user, name)` :
  1. clôture la saison active courante ;
  2. crée une nouvelle saison active ;
  3. recopie la géométrie de chaque `Bac` non archivé dans un nouveau `BacSaison`
     (taille physique + position depuis le dernier `BacSaison` ou les défauts du `Bac`,
     découpage lignes/colonnes idem) ;
  4. recopie les `Culture` **pérennes** `en_place` dans les `BacSaison` correspondants,
     même placement, en liant `parentCulture` ;
  5. grainothèque inchangée ; semis/annuelles non reportés.
- Front : liste/formulaire des bacs (taille physique + découpage par défaut), édition du
  découpage de la saison courante, branchement réel du bouton « Nouvelle saison » (Phase 2).
- Tests API : snapshot géométrie, immutabilité des saisons passées, taille physique figée
  vs découpage éditable, report pérennes, reset annuelles.
- **Fichiers** : `src/Entity/Bac.php`, `src/Entity/BacSaison.php`,
  `src/Repository/*`, `src/Controller/Api/BacApiController.php`,
  `src/Controller/Api/BacSaisonApiController.php`, `src/Service/SaisonCycleService.php`,
  `assets/src/views/bac/*`, `tests/Controller/Api/BacApiTest.php`,
  `tests/Controller/Api/SaisonCycleTest.php`.

### Phase 5 — Cultures / Plantations
- Entité `Culture` (+ migration).
- API `CultureApiController` (calquer `VegetableApiController` : liste filtrée par
  saison/bac, placement, statut, lien semis→culture lors de la mise en bac — bascule le
  `Semis.statut` à `plante`). Validation placement (bornes + chevauchement). Écriture
  refusée si saison clôturée.
- Front : pose d'un plant dans un bac (depuis un semis « repiqué » ou ajout direct),
  édition statut/dates/pérenne, liste par bac.
- Tests API : CRUD, validation placement/chevauchement, lien semis, blocage saison
  clôturée.
- **Fichiers** : `src/Entity/Culture.php`, `src/Repository/CultureRepository.php`,
  `src/Controller/Api/CultureApiController.php`, `assets/src/views/culture/*`,
  `tests/Controller/Api/CultureApiTest.php`.

### Phase 6 — Vue plan visuelle
- Vue Vue dédiée : plan du potager (bacs positionnés via `posX/posY`, à l'échelle de leur
  taille physique), zoom sur un bac = grille `lignes × colonnes`, cases occupées par les
  cultures (`posX/posY` + emprise).
- Interaction : placement/déplacement par drag-drop case par case (HTML5 natif d'abord,
  sinon dépendance légère type `vue-draggable-plus` si nécessaire — décision au début de
  la phase). **Lecture seule** si la saison sélectionnée est clôturée.
- Rendu : SVG ou CSS Grid (pas de nouvelle dépendance lourde ; pas de canvas-lib existante
  dans le projet — cf. `package.json`).
- Réutiliser le thème vert (`assets/src/css/theme.css`).
- **Fichiers** : `assets/src/views/potager/PlanView.vue`,
  `assets/src/components/potager/BacGrid.vue` (+ éventuel `CultureCell.vue`),
  `assets/src/router/index.js`.

---

## Vérification (par phase et globale)

- **API** : `make test-api` (ou un test ciblé `make bash` → `bin/phpunit --filter …`)
  après chaque phase ; ajouter les tests décrits ci-dessus avant de clore une phase.
- **Migrations** : dans le conteneur, `doctrine:migrations:diff` puis `migrate` ; vérifier
  que les tables créées sont bien préfixées `plant_*`.
- **Front** : `make front-dev` (HMR) pour valider les écrans ; `make front-build` pour le
  build de prod. Parcours manuel par phase :
  - P1 : créer un GraineType `CGT1`, ajouter 2 lots, voir le stock cumulé décroître.
  - P2 : créer une saison, la sélectionner dans la navbar, vérifier le scope.
  - P3 : semer depuis un lot, voir la quantité du lot diminuer, suivre le statut.
  - P4 : créer des bacs, démarrer une nouvelle saison → géométrie recopiée, pérennes
    reportées, annuelles absentes ; modifier le découpage en cours de saison (OK), tenter
    de modifier la taille physique (refus) ; ouvrir une saison clôturée → écriture refusée.
  - P5 : poser un plant dans une case, tenter un chevauchement (refus), réduire le
    découpage sous une culture (refus).
  - P6 : visualiser le plan, déplacer un plant ; ouvrir une saison passée → plan en
    lecture seule.
- **Global** : `make test` (suite complète) avant de considérer le module abouti.

---

## Notes / points d'attention

- **`Group` (mot réservé)** : précédent existant de nom de table quoté ; aucune nouvelle
  entité ne porte de nom réservé ici, mais garder le réflexe si ajout.
- **Charset** : conserver `&charset=utf8mb4` (cf. CLAUDE.md) — nouvelles colonnes texte en
  utf8mb4.
- **Photos** (optionnel, hors périmètre initial) : pour des photos de semis/cultures plus
  tard, réutiliser `PhotoUploader.vue` / `PhotoGallery.vue` + `PhotoPresenter` — prévoir
  une FK nullable, non bloquant pour les 6 phases.
