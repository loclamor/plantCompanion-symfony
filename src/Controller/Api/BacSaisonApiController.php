<?php

namespace App\Controller\Api;

use App\Entity\BacSaison;
use App\Entity\Saison;
use App\Entity\UserOwnedInterface;
use App\Entity\Utilisateur;
use App\Exception\ClosedSeasonException;
use App\Repository\BacRepository;
use App\Repository\BacSaisonRepository;
use App\Repository\CultureRepository;
use App\Repository\SaisonRepository;
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
 * API JSON des snapshots de bacs par saison (module Potager). La taille physique
 * (largeur/longueur/posX/posY) est figée une fois la saison démarrée ; seul le
 * découpage (lignes/colonnes) reste modifiable tant que la saison est active.
 * Écriture refusée (409) sur une saison clôturée (SeasonGuard).
 */
#[Route('/api/bac-saisons')]
final class BacSaisonApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BacSaisonRepository $bacSaisons,
        private readonly BacRepository $bacs,
        private readonly SaisonRepository $saisons,
        private readonly CultureRepository $cultures,
        private readonly CurrentSeason $currentSeason,
        private readonly SeasonGuard $seasonGuard,
    ) {
    }

    #[Route('', name: 'api_bac_saisons_index', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $saison = $this->resolveOwned($this->saisons, $request->query->get('saison'), $user)
            ?? $this->currentSeason->resolve($user);

        $rows = null !== $saison ? $this->bacSaisons->findByUserAndSaison($user, $saison) : [];

        return new JsonResponse(\App\Service\Utf8::clean([
            'saison' => $saison ? ['id' => $saison->getId(), 'name' => $saison->getName(), 'statut' => $saison->getStatut()] : null,
            'items' => array_map(fn (BacSaison $bs) => $this->serialize($bs), $rows),
        ]));
    }

    #[Route('/{id}', name: 'api_bac_saison_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(BacSaison $bacSaison): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::VIEW, $bacSaison);

        return new JsonResponse(\App\Service\Utf8::clean($this->serialize($bacSaison)));
    }

    #[Route('', name: 'api_bac_saison_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $bac = $this->resolveOwned($this->bacs, $data['bac'] ?? null, $user);
        if (null === $bac) {
            return new JsonResponse(['errors' => ['bac' => 'Bac invalide ou obligatoire.']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $saison = $this->resolveOwned($this->saisons, $data['saison'] ?? null, $user)
            ?? $this->currentSeason->resolve($user);
        if (null === $saison) {
            return new JsonResponse(['errors' => ['saison' => 'Aucune saison sélectionnée.']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (null !== $resp = $this->guard($saison)) {
            return $resp;
        }

        $bacSaison = (new BacSaison())
            ->setUtilisateur($user)
            ->setBac($bac)
            ->setSaison($saison);

        // Géométrie : payload, sinon défauts du bac.
        $bacSaison
            ->setLargeur($this->intOr($data['largeur'] ?? null, $bac->getLargeurDefaut()))
            ->setLongueur($this->intOr($data['longueur'] ?? null, $bac->getLongueurDefaut()))
            ->setPosX($this->intOr($data['posX'] ?? null, 0))
            ->setPosY($this->intOr($data['posY'] ?? null, 0));

        $errors = $this->applyDecoupage($bacSaison, $data, $bac);
        if ([] !== $errors) {
            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($bacSaison);
        $this->em->flush();

        return new JsonResponse(\App\Service\Utf8::clean($this->serialize($bacSaison)), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_bac_saison_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Request $request, BacSaison $bacSaison): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $bacSaison);
        if (null !== $resp = $this->guard($bacSaison->getSaison())) {
            return $resp;
        }

        $data = json_decode($request->getContent(), true) ?? [];

        // Taille physique figée : tout changement de largeur/longueur est refusé (409).
        // La position (posX/posY) et le découpage restent modifiables tant que la saison
        // est active (drag-drop du plan, cf. Phase 6).
        foreach (['largeur', 'longueur'] as $frozen) {
            if (\array_key_exists($frozen, $data) && (int) $data[$frozen] !== $this->currentFrozen($bacSaison, $frozen)) {
                return new JsonResponse([
                    'message' => 'La taille physique du bac est figée pour la saison ; position et découpage restent modifiables.',
                ], Response::HTTP_CONFLICT);
            }
        }

        // Position dans le plan du potager : éditable (saison active).
        if (\array_key_exists('posX', $data)) {
            $bacSaison->setPosX(max(0, $this->intOr($data['posX'], $bacSaison->getPosX())));
        }
        if (\array_key_exists('posY', $data)) {
            $bacSaison->setPosY(max(0, $this->intOr($data['posY'], $bacSaison->getPosY())));
        }

        $errors = $this->applyDecoupage($bacSaison, $data, $bacSaison->getBac());
        if ([] !== $errors) {
            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Resserrer le découpage ne doit pas laisser de culture « en_place » hors bornes.
        $conflicts = $this->culturesOutOfBounds($bacSaison);
        if ([] !== $conflicts) {
            return new JsonResponse([
                'message' => 'Le découpage est trop petit : des cultures en place sortiraient de la grille.',
                'conflicts' => $conflicts,
            ], Response::HTTP_CONFLICT);
        }

        $this->em->flush();

        return new JsonResponse(\App\Service\Utf8::clean($this->serialize($bacSaison)));
    }

    /**
     * Cultures « en_place » du bac qui ne tiennent plus dans lignes×colonnes.
     *
     * @return array<int, array{id: int|null, name: string|null}>
     */
    private function culturesOutOfBounds(BacSaison $bacSaison): array
    {
        $conflicts = [];
        foreach ($this->cultures->findEnPlaceByBacSaison($bacSaison) as $culture) {
            $outX = $culture->getPosX() + $culture->getLargeurCases() > $bacSaison->getColonnes();
            $outY = $culture->getPosY() + $culture->getHauteurCases() > $bacSaison->getLignes();
            if ($outX || $outY) {
                $conflicts[] = ['id' => $culture->getId(), 'name' => $culture->getName()];
            }
        }

        return $conflicts;
    }

    #[Route('/{id}', name: 'api_bac_saison_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(BacSaison $bacSaison): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::DELETE, $bacSaison);
        if (null !== $resp = $this->guard($bacSaison->getSaison())) {
            return $resp;
        }

        $this->em->remove($bacSaison);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Applique le découpage (lignes/colonnes) modifiable. Retourne les erreurs.
     *
     * Phase 5 : refuser la réduction du découpage sous une Culture hors limites
     * (409 + liste des cultures en conflit).
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    private function applyDecoupage(BacSaison $bacSaison, array $data, ?\App\Entity\Bac $bac): array
    {
        $errors = [];

        $lignes = $this->positiveInt($data['lignes'] ?? null, $bacSaison->getId() ? $bacSaison->getLignes() : ($bac?->getLignesDefaut() ?? 1));
        if (null === $lignes) {
            $errors['lignes'] = 'Le nombre de lignes doit être un entier ≥ 1.';
        } else {
            $bacSaison->setLignes($lignes);
        }

        $colonnes = $this->positiveInt($data['colonnes'] ?? null, $bacSaison->getId() ? $bacSaison->getColonnes() : ($bac?->getColonnesDefaut() ?? 1));
        if (null === $colonnes) {
            $errors['colonnes'] = 'Le nombre de colonnes doit être un entier ≥ 1.';
        } else {
            $bacSaison->setColonnes($colonnes);
        }

        return $errors;
    }

    private function currentFrozen(BacSaison $bs, string $field): int
    {
        return match ($field) {
            'largeur' => $bs->getLargeur(),
            'longueur' => $bs->getLongueur(),
            'posX' => $bs->getPosX(),
            'posY' => $bs->getPosY(),
        };
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

    /** Entier du payload (clé présente), sinon valeur par défaut. */
    private function intOr(mixed $v, int $default): int
    {
        return (null === $v || '' === $v || !is_numeric($v)) ? $default : (int) $v;
    }

    /** Entier ≥ 1 si fourni et valide, valeur courante si absent, null si invalide. */
    private function positiveInt(mixed $v, int $current): ?int
    {
        if (null === $v || '' === $v) {
            return $current;
        }
        if (!is_numeric($v)) {
            return null;
        }
        $n = (int) $v;

        return $n >= 1 ? $n : null;
    }

    private function resolveOwned(object $repository, mixed $id, Utilisateur $user): ?object
    {
        if (null === $id || '' === $id) {
            return null;
        }
        $entity = $repository->find((int) $id);

        return ($entity instanceof UserOwnedInterface && $entity->getUtilisateur() === $user) ? $entity : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(BacSaison $bs): array
    {
        $bac = $bs->getBac();

        return [
            'id' => $bs->getId(),
            'bac' => $bac ? ['id' => $bac->getId(), 'name' => $bac->getName(), 'archived' => $bac->isArchived()] : null,
            'saison' => $bs->getSaison() ? ['id' => $bs->getSaison()->getId(), 'name' => $bs->getSaison()->getName(), 'statut' => $bs->getSaison()->getStatut()] : null,
            'largeur' => $bs->getLargeur(),
            'longueur' => $bs->getLongueur(),
            'posX' => $bs->getPosX(),
            'posY' => $bs->getPosY(),
            'lignes' => $bs->getLignes(),
            'colonnes' => $bs->getColonnes(),
        ];
    }
}
