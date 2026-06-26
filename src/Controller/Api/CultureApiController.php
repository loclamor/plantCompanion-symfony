<?php

namespace App\Controller\Api;

use App\Entity\BacSaison;
use App\Entity\Culture;
use App\Entity\Recolte;
use App\Entity\Saison;
use App\Entity\Semis;
use App\Entity\Utilisateur;
use App\Exception\ClosedSeasonException;
use App\Repository\BacSaisonRepository;
use App\Repository\CultureRepository;
use App\Repository\GraineTypeRepository;
use App\Repository\RecolteRepository;
use App\Repository\SaisonRepository;
use App\Repository\SemisRepository;
use App\Security\Voter\OwnerVoter;
use App\Service\CurrentSeason;
use App\Service\SeasonGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * API JSON des cultures (module Potager) : un plant posé dans une case d'un bac
 * sur une saison. Calque SemisApiController (scope saison courante, SeasonGuard,
 * resolveOwned/serialize). Valide le placement (bornes de grille + chevauchement
 * entre cultures « en_place » du même bac). Quand une culture est issue d'un
 * semis, ce dernier bascule « planté ».
 */
#[Route('/api/cultures')]
final class CultureApiController extends AbstractController
{
    private const STATUTS = [Culture::STATUT_EN_PLACE, Culture::STATUT_RECOLTE, Culture::STATUT_MORT];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CultureRepository $cultures,
        private readonly SaisonRepository $saisons,
        private readonly BacSaisonRepository $bacSaisons,
        private readonly GraineTypeRepository $graineTypes,
        private readonly SemisRepository $semis,
        private readonly RecolteRepository $recoltes,
        private readonly CurrentSeason $currentSeason,
        private readonly SeasonGuard $seasonGuard,
    ) {
    }

    #[Route('', name: 'api_cultures_index', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $saison = $this->resolveOwned($this->saisons, $request->query->get('saison'), $user)
            ?? $this->currentSeason->resolve($user);

        $bacSaison = $this->resolveOwned($this->bacSaisons, $request->query->get('bacSaison'), $user);

        $statut = $request->query->getString('statut');
        $statut = \in_array($statut, self::STATUTS, true) ? $statut : null;

        $rows = $this->cultures->findByUserFiltered($user, $saison, $bacSaison, $statut);

        return new JsonResponse(\App\Service\Utf8::clean([
            'saison' => $saison ? ['id' => $saison->getId(), 'name' => $saison->getName(), 'statut' => $saison->getStatut()] : null,
            'items' => array_map(fn (Culture $c) => $this->serialize($c), $rows),
        ]));
    }

    #[Route('/{id}', name: 'api_culture_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Culture $culture): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::VIEW, $culture);

        return new JsonResponse(\App\Service\Utf8::clean($this->serialize($culture)));
    }

    #[Route('', name: 'api_culture_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $culture = (new Culture())->setUtilisateur($user);

        $errors = $this->apply($culture, $data, $user);
        if ([] !== $errors) {
            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (null !== $resp = $this->guard($culture->getSaison())) {
            return $resp;
        }

        $placement = $this->validatePlacement($culture);
        if (null !== $placement) {
            return $placement;
        }

        $this->linkSemis($culture);

        $this->em->persist($culture);
        $this->em->flush();

        return new JsonResponse(\App\Service\Utf8::clean($this->serialize($culture)), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_culture_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Request $request, Culture $culture, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $culture);
        if (null !== $resp = $this->guard($culture->getSaison())) {
            return $resp;
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $errors = $this->apply($culture, $data, $user);
        if ([] !== $errors) {
            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        // La nouvelle saison (si changée) doit aussi être ouverte.
        if (null !== $resp = $this->guard($culture->getSaison())) {
            return $resp;
        }

        $placement = $this->validatePlacement($culture);
        if (null !== $placement) {
            return $placement;
        }

        $this->linkSemis($culture);

        $this->em->flush();

        return new JsonResponse(\App\Service\Utf8::clean($this->serialize($culture)));
    }

    #[Route('/{id}', name: 'api_culture_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Culture $culture): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::DELETE, $culture);
        if (null !== $resp = $this->guard($culture->getSaison())) {
            return $resp;
        }

        $this->em->remove($culture);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/placement', name: 'api_culture_placement', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function placement(Request $request, Culture $culture, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $culture);
        if (null !== $resp = $this->guard($culture->getSaison())) {
            return $resp;
        }

        $data = json_decode($request->getContent(), true) ?? [];

        // Déplacement inter-bac optionnel ; sinon on garde le bac courant.
        if (\array_key_exists('bacSaison', $data)) {
            $bacSaison = $this->resolveOwned($this->bacSaisons, $data['bacSaison'], $user);
            if (null === $bacSaison) {
                return new JsonResponse(['errors' => ['bacSaison' => 'Bac de la saison invalide.']], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $culture->setBacSaison($bacSaison);
            $culture->setSaison($bacSaison->getSaison());
        }

        $culture->setPosX($this->intOr($data['posX'] ?? null, $culture->getPosX()));
        $culture->setPosY($this->intOr($data['posY'] ?? null, $culture->getPosY()));

        $placement = $this->validatePlacement($culture);
        if (null !== $placement) {
            return $placement;
        }

        $this->em->flush();

        return new JsonResponse(\App\Service\Utf8::clean($this->serialize($culture)));
    }

    #[Route('/{id}/recoltes', name: 'api_culture_recolte_add', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addRecolte(Request $request, Culture $culture, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $culture);
        if (null !== $resp = $this->guard($culture->getSaison())) {
            return $resp;
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $date = $this->parseDate($data['date'] ?? null);
        if (null === $date) {
            return new JsonResponse(['errors' => ['date' => 'Date de récolte invalide ou obligatoire.']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $unite = $data['unite'] ?? Recolte::UNITE_PIECES;
        if (!\in_array($unite, [Recolte::UNITE_PIECES, Recolte::UNITE_G, Recolte::UNITE_KG], true)) {
            return new JsonResponse(['errors' => ['unite' => 'Unité invalide (pieces, g ou kg).']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $quantite = $data['quantite'] ?? null;
        $recolte = (new Recolte())
            ->setUtilisateur($user)
            ->setDate($date)
            ->setQuantite(is_numeric($quantite) ? (float) $quantite : null)
            ->setUnite($unite)
            ->setNotes($this->nullableString($data['notes'] ?? null));
        $culture->addRecolte($recolte);
        $this->em->persist($recolte);
        $this->em->flush();

        return new JsonResponse(\App\Service\Utf8::clean($this->serialize($culture)), Response::HTTP_CREATED);
    }

    #[Route('/{id}/recoltes/{rid}', name: 'api_culture_recolte_delete', methods: ['DELETE'], requirements: ['id' => '\d+', 'rid' => '\d+'])]
    public function deleteRecolte(Culture $culture, int $rid): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $culture);
        if (null !== $resp = $this->guard($culture->getSaison())) {
            return $resp;
        }

        $recolte = $this->recoltes->find($rid);
        if (null === $recolte || $recolte->getCulture() !== $culture) {
            return new JsonResponse(['message' => 'Récolte introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $culture->removeRecolte($recolte);
        $this->em->remove($recolte);
        $this->em->flush();

        return new JsonResponse(\App\Service\Utf8::clean($this->serialize($culture)));
    }

    /**
     * Remplit une culture depuis le payload. Retourne les erreurs (vide si OK).
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    private function apply(Culture $culture, array $data, Utilisateur $user): array
    {
        $errors = [];

        // Bac (snapshot saison) obligatoire. Sa saison pilote celle de la culture.
        $bacSaison = $this->resolveOwned($this->bacSaisons, $data['bacSaison'] ?? null, $user);
        if (null === $bacSaison) {
            $errors['bacSaison'] = 'Bac de la saison invalide ou obligatoire.';
        } else {
            $culture->setBacSaison($bacSaison);
            $culture->setSaison($bacSaison->getSaison());
        }

        $culture->setGraineType($this->resolveOwned($this->graineTypes, $data['graineType'] ?? null, $user));
        $culture->setSemis($this->resolveOwned($this->semis, $data['semis'] ?? null, $user));

        $name = $this->nullableString($data['name'] ?? null);
        if (null === $name) {
            $errors['name'] = 'Le nom est obligatoire.';
        } else {
            $culture->setName($name);
        }

        $culture->setPosX($this->intOr($data['posX'] ?? null, 0));
        $culture->setPosY($this->intOr($data['posY'] ?? null, 0));
        $culture->setLargeurCases(max(1, $this->intOr($data['largeurCases'] ?? null, 1)));
        $culture->setHauteurCases(max(1, $this->intOr($data['hauteurCases'] ?? null, 1)));

        $datePlantation = $this->parseDate($data['datePlantation'] ?? null);
        if (null === $datePlantation) {
            $errors['datePlantation'] = 'Date de plantation invalide ou obligatoire.';
        } else {
            $culture->setDatePlantation($datePlantation);
        }

        $culture->setDateRecolteTheorique($this->parseDate($data['dateRecolteTheorique'] ?? null));
        $culture->setDateFin($this->parseDate($data['dateFin'] ?? null));

        $statut = $data['statut'] ?? Culture::STATUT_EN_PLACE;
        if (\in_array($statut, self::STATUTS, true)) {
            $culture->setStatut($statut);
        } else {
            $errors['statut'] = 'Statut invalide.';
        }

        $culture->setPerenne((bool) ($data['perenne'] ?? false));

        return $errors;
    }

    /**
     * Valide le placement : bornes de la grille du bac + non-chevauchement avec
     * les cultures « en_place » du même bac (hors elle-même). 422 si KO, null si OK.
     */
    private function validatePlacement(Culture $culture): ?JsonResponse
    {
        $bs = $culture->getBacSaison();
        if (null === $bs) {
            return null;
        }

        $x = $culture->getPosX();
        $y = $culture->getPosY();
        $w = $culture->getLargeurCases();
        $h = $culture->getHauteurCases();

        if ($x < 0 || $y < 0 || $x + $w > $bs->getColonnes() || $y + $h > $bs->getLignes()) {
            return new JsonResponse([
                'errors' => ['placement' => \sprintf(
                    'La culture sort de la grille du bac (%d×%d cases).',
                    $bs->getColonnes(),
                    $bs->getLignes(),
                )],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $conflicts = [];
        foreach ($this->cultures->findEnPlaceByBacSaison($bs) as $other) {
            if ($other === $culture || $other->getId() === $culture->getId()) {
                continue;
            }
            if ($this->overlaps($x, $y, $w, $h, $other)) {
                $conflicts[] = ['id' => $other->getId(), 'name' => $other->getName()];
            }
        }

        if ([] !== $conflicts) {
            return new JsonResponse([
                'errors' => ['placement' => 'La culture chevauche une autre culture en place.'],
                'conflicts' => $conflicts,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return null;
    }

    private function overlaps(int $x, int $y, int $w, int $h, Culture $other): bool
    {
        return $x < $other->getPosX() + $other->getLargeurCases()
            && $x + $w > $other->getPosX()
            && $y < $other->getPosY() + $other->getHauteurCases()
            && $y + $h > $other->getPosY();
    }

    /**
     * Lien semis → culture : à la mise en bac, le semis bascule « planté »
     * (datePlantation posée si absente, statut recalculé).
     */
    private function linkSemis(Culture $culture): void
    {
        $semis = $culture->getSemis();
        if (null === $semis) {
            return;
        }
        if (null === $semis->getDatePlantation()) {
            $semis->setDatePlantation($culture->getDatePlantation());
        }
        $semis->recomputeStatut();
    }

    /**
     * Garde « saison clôturée = lecture seule ». 409 si fermée, sinon null.
     */
    private function guard(?Saison $saison): ?JsonResponse
    {
        if (null === $saison) {
            return null;
        }
        try {
            $this->seasonGuard->assertWritable($saison);
        } catch (ClosedSeasonException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return null;
    }

    private function resolveOwned(object $repository, mixed $id, Utilisateur $user): ?object
    {
        if (null === $id || '' === $id) {
            return null;
        }
        $entity = $repository->find((int) $id);

        return ($entity instanceof \App\Entity\UserOwnedInterface && $entity->getUtilisateur() === $user) ? $entity : null;
    }

    private function intOr(mixed $v, int $default): int
    {
        return (null === $v || '' === $v || !is_numeric($v)) ? $default : (int) $v;
    }

    private function nullableString(mixed $v): ?string
    {
        $v = is_string($v) ? trim($v) : $v;

        return (null === $v || '' === $v) ? null : (string) $v;
    }

    private function parseDate(mixed $v): ?\DateTimeImmutable
    {
        if (!is_string($v) || '' === trim($v)) {
            return null;
        }

        try {
            return (new \DateTimeImmutable($v))->setTime(0, 0);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Culture $c): array
    {
        $bs = $c->getBacSaison();
        $bac = $bs?->getBac();

        return [
            'id' => $c->getId(),
            'saison' => $c->getSaison() ? ['id' => $c->getSaison()->getId(), 'name' => $c->getSaison()->getName(), 'statut' => $c->getSaison()->getStatut()] : null,
            'bacSaison' => $bs ? [
                'id' => $bs->getId(),
                'bac' => $bac ? ['id' => $bac->getId(), 'name' => $bac->getName()] : null,
                'lignes' => $bs->getLignes(),
                'colonnes' => $bs->getColonnes(),
            ] : null,
            'graineType' => $c->getGraineType() ? [
                'id' => $c->getGraineType()->getId(),
                'name' => $c->getGraineType()->getName(),
                'code' => $c->getGraineType()->getCode(),
            ] : null,
            'semis' => $c->getSemis() ? ['id' => $c->getSemis()->getId(), 'statut' => $c->getSemis()->getStatut()] : null,
            'name' => $c->getName(),
            'posX' => $c->getPosX(),
            'posY' => $c->getPosY(),
            'largeurCases' => $c->getLargeurCases(),
            'hauteurCases' => $c->getHauteurCases(),
            'datePlantation' => $c->getDatePlantation()?->format('Y-m-d'),
            'dateRecolteTheorique' => $c->getDateRecolteTheorique()?->format('Y-m-d'),
            'dateFin' => $c->getDateFin()?->format('Y-m-d'),
            'statut' => $c->getStatut(),
            'perenne' => $c->isPerenne(),
            'parentCulture' => $c->getParentCulture() ? ['id' => $c->getParentCulture()->getId()] : null,
            'recoltes' => array_map(static fn (Recolte $r) => [
                'id' => $r->getId(),
                'date' => $r->getDate()?->format('Y-m-d'),
                'quantite' => $r->getQuantite(),
                'unite' => $r->getUnite(),
                'notes' => $r->getNotes(),
            ], $c->getRecoltes()->toArray()),
        ];
    }
}
