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

### Phase 3 — Semis (scopé saison, consomme la grainothèque) ✅ FAIT
**Évolution du modèle vs plan initial** : suivi **individuel au plant** plutôt
qu'un lot à statut unique. **1 graine semée = 1 ligne `Semis`** (ses propres
dates semis/levée/plantation) + **rempotages 0..n** (entité `Rempotage`).
- Entités `Semis` (saison, graineType, graineLot?, methode `direct|godet`,
  dateSemis, dateLevee?, datePlantation?, dates théoriques, `echec`, statut
  **dérivé** `seme|leve|plante|echec`, notes) + `Rempotage` (semis, date, notes?).
  Migration `Version20260623124458` (tables `plant_semis`, `plant_rempotage`).
- API `SemisApiController` (calque `VegetableApiController`) : liste filtrée
  saison (courante via `CurrentSeason`) + `statut`/`graineType` (sans pagination),
  CRUD, **`POST /api/semis/batch`** (saisie groupée : N semis par entrée, mix de
  types), rempotages imbriqués `POST/DELETE /api/semis/{id}/rempotages[/{rid}]`.
  **Décrément + restitution** du `GraineLot` (−1 à la création/batch, ajustement
  au changement de lot, +1 à la suppression). **Écriture refusée 409 si saison
  clôturée** (premier consommateur de `SeasonGuard`).
- Front : `SemisList` (regroupement par type+date+méthode, dépliable, actions
  rapides levé/rempoter/planté/échec/éditer/supprimer, filtres statut/type,
  scope saison courante), `SemisForm` (cascade type→graine→lot), `SemisBatchForm`
  (saisie multi-lignes). Menu « Semis » dans le potager.
- Tests : `SemisApiTest` (CRUD, décrément, batch, restitution update/delete,
  rempotages, statut dérivé, filtres, blocage saison clôturée, isolation).
- **Fichiers** : `src/Entity/{Semis,Rempotage}.php`,
  `src/Repository/{SemisRepository,RempotageRepository}.php`,
  `src/Controller/Api/SemisApiController.php`,
  `assets/src/views/semis/{SemisList,SemisForm,SemisBatchForm}.vue`,
  `assets/src/router/index.js`, `assets/src/App.vue`,
  `tests/Controller/Api/SemisApiTest.php`.

### Phase 4 — Bacs + cycle de saison ✅ FAIT
- Entités `Bac` (identité logique : `name`, `largeurDefaut`/`longueurDefaut`,
  `lignesDefaut`/`colonnesDefaut`, `archived`) + `BacSaison` (snapshot : `bac`, `saison`,
  taille physique `largeur`/`longueur`/`posX`/`posY` **figée**, découpage `lignes`/`colonnes`
  **éditable**). Migration `Version20260623174518` (tables `plant_bac`, `plant_bac_saison`).
- API : `BacApiController` (`/api/bacs`, CRUD via `AbstractOwnedCrudApiController` +
  `PUT /api/bacs/{id}/archiver`), `BacSaisonApiController` (`/api/bac-saisons`, calque
  `SemisApiController` : liste scopée saison courante, taille physique **figée** → 409 sur
  tentative de changement, découpage modifiable si saison active, **écriture refusée 409 si
  saison clôturée** via `SeasonGuard`).
- Service `App\Service\SaisonCycleService.startNewSeason(user, Saison)` :
  1. clôture la saison active courante ;
  2. persiste la nouvelle saison active ;
  3. recopie la géométrie de chaque `Bac` non archivé dans un nouveau `BacSaison`
     (depuis le dernier `BacSaison` du bac via `BacSaisonRepository::findLastForBac`, sinon
     les défauts du `Bac`) ;
  4. **report des `Culture` pérennes : différé en Phase 5** (l'entité `Culture` n'existe pas
     encore — point d'extension laissé dans le service) ;
  5. grainothèque inchangée ; semis/annuelles non reportés.
  Exposé via `POST /api/saisons/new-cycle` (dans `SaisonApiController`), appelé par la
  création de saison du front (`SaisonForm`).
- Front : `BacList` (définition des bacs + édition inline du découpage de la saison courante,
  lecture seule si clôturée) / `BacForm`, menu « Potager › Bacs », routes `/potager/bacs`.
  Création d'une saison branchée sur le cycle (report géométrie).
- Tests API : `BacApiTest` (CRUD, archivage, 422 dimensions, isolation), `BacSaisonApiTest`
  (scope saison, taille figée, découpage éditable, blocage saison clôturée, isolation),
  `SaisonCycleTest` (clôture+nouvelle active, recopie géométrie, immutabilité du passé, bacs
  archivés non reportés, défauts au 1er report, validation payload).
- **Fichiers** : `src/Entity/{Bac,BacSaison}.php`,
  `src/Repository/{BacRepository,BacSaisonRepository}.php`,
  `src/Controller/Api/{BacApiController,BacSaisonApiController}.php`,
  `src/Service/SaisonCycleService.php`, `migrations/Version20260623174518.php`,
  endpoint `new-cycle` dans `SaisonApiController`, `assets/src/views/bac/{BacList,BacForm}.vue`,
  `assets/src/{router/index.js,App.vue,views/saison/SaisonForm.vue}`,
  `tests/Controller/Api/{BacApiTest,BacSaisonApiTest,SaisonCycleTest}.php`.

### Phase 5 — Cultures / Plantations ✅ FAIT
- Entité `Culture` (saison, bacSaison, graineType?, semis?, name, posX/posY,
  largeurCases/hauteurCases, datePlantation, dateRecolteTheorique?, dateFin?, statut
  `en_place|recolte|mort`, perenne, parentCulture self-ref) + migration
  `Version20260625080824` (table `plant_culture`).
- API `CultureApiController` (calque `SemisApiController` : scope saison courante via
  `CurrentSeason`, `SeasonGuard`) : liste filtrée `?saison/?bacSaison/?statut`, CRUD.
  **Validation de placement** : bornes de la grille + non-chevauchement avec les cultures
  `en_place` du même bac (422 + liste des conflits ; les `recolte`/`mort` ne bloquent pas).
  **Lien semis** : à la mise en bac, le `Semis` bascule `plante` (datePlantation posée si
  absente, statut recalculé). Écriture refusée 409 si saison clôturée.
- Garde « réduire le découpage sous une culture » branchée dans
  `BacSaisonApiController.update` (409 + conflits si resserrement laisse une culture
  `en_place` hors bornes).
- **Report des pérennes** dans `SaisonCycleService.startNewSeason` (étape 4) : les cultures
  `en_place` + `perenne` sont recopiées dans le `BacSaison` du même bac de la nouvelle
  saison (lignage `parentCulture`, **`datePlantation` d'origine conservée**, semis non
  reporté). Bacs archivés (pas de nouveau snapshot) → cultures non reportées.
- Front : `CultureList` (liste de la saison courante, filtres bac/statut, actions rapides
  récolté/mort/éditer/supprimer, lecture seule si clôturée), `CultureForm` (**sélecteur de
  case cliquable** = grille `lignes×colonnes` du bac, cases occupées grisées ; emprise
  largeur/hauteur ; origine semis « levé » optionnelle ou ajout direct ; dates, statut,
  pérenne). Menu « Potager › Cultures », routes `/potager/cultures`.
- Tests API : `CultureApiTest` (CRUD, scope/filtres, bornes, chevauchement, autorisé sur
  `recolte`, adjacence, update sans auto-conflit, lien semis→planté, 409 clôturée,
  isolation) ; `SaisonCycleTest` complété (report pérennes uniquement, bac archivé exclu) ;
  `BacSaisonApiTest` complété (refus resserrement). Suite complète verte (123 tests).
- **Récoltes multiples** : entité `Recolte` (0..n par culture, calque `Rempotage` : date,
  quantité?, unité `pieces|g|kg`, notes) + migration `Version20260625093607`
  (`plant_recolte`). Endpoints imbriqués `POST /api/cultures/{id}/recoltes` et
  `DELETE /api/cultures/{id}/recoltes/{rid}`, sérialisées dans la culture. La culture
  **reste `en_place`** quand on ajoute des récoltes ; `recolte` est un statut final posé
  manuellement. Géré inline dans `CultureForm` (mode édition).
- **Décisions** : UI placement = sélecteur de case cliquable (drag-drop reporté Phase 6) ;
  pérenne reportée conserve sa `datePlantation` d'origine ; récoltes multiples par culture
  (rendement cumulé) sans changer le statut.
- **Fichiers** : `src/Entity/{Culture,Recolte}.php`,
  `src/Repository/{CultureRepository,RecolteRepository}.php`,
  `src/Controller/Api/CultureApiController.php`,
  `migrations/{Version20260625080824,Version20260625093607}.php`,
  `src/Service/SaisonCycleService.php`, `src/Controller/Api/BacSaisonApiController.php`,
  `assets/src/views/culture/{CultureList,CultureForm}.vue`,
  `assets/src/{router/index.js,App.vue}`,
  `tests/Controller/Api/{CultureApiTest,SaisonCycleTest,BacSaisonApiTest}.php`.

### Phase 6 — Vue plan visuelle
- **Pré-requis** : relâcher le gel de `posX/posY` du `BacSaison` (actuellement figé,
  409 dans `BacSaisonApiController::update` lignes ~119-125) pour permettre le
  repositionnement des bacs par drag-drop tant que la saison est `active` ; garder
  `largeur/longueur` figés. À acter au début de la phase.
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
