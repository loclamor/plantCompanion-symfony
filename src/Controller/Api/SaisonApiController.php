<?php

namespace App\Controller\Api;

use App\Entity\Saison;
use App\Entity\UserOwnedInterface;
use App\Entity\Utilisateur;
use App\Repository\SaisonRepository;
use App\Security\Voter\OwnerVoter;
use App\Service\SaisonCycleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/saisons')]
final class SaisonApiController extends AbstractOwnedCrudApiController
{
    public function __construct(
        EntityManagerInterface $em,
        private readonly SaisonRepository $repo,
        private readonly SaisonCycleService $cycle,
    ) {
        parent::__construct($em);
    }

    protected function getRepository(): object
    {
        return $this->repo;
    }

    protected function createEntity(Utilisateur $user): UserOwnedInterface
    {
        return (new Saison())->setUtilisateur($user)->setStatut(Saison::STATUT_ACTIVE);
    }

    protected function applyPayload(UserOwnedInterface $entity, array $data, Utilisateur $user): array
    {
        \assert($entity instanceof Saison);
        $errors = [];

        if (null !== $err = $this->applyName($entity, $data)) {
            $errors['name'] = $err;
        }

        $annee = $data['annee'] ?? null;
        if (null === $annee || '' === $annee || !is_numeric($annee)) {
            $errors['annee'] = "L'année est obligatoire.";
        } else {
            $entity->setAnnee((int) $annee);
        }

        $dateDebut = $this->parseDate($data['dateDebut'] ?? null);
        if (null === $dateDebut) {
            $errors['dateDebut'] = 'La date de début est obligatoire (format AAAA-MM-JJ).';
        } else {
            $entity->setDateDebut($dateDebut);
        }

        $rawFin = $data['dateFin'] ?? null;
        if (null === $rawFin || '' === $rawFin) {
            $entity->setDateFin(null);
        } elseif (null !== $dateFin = $this->parseDate($rawFin)) {
            $entity->setDateFin($dateFin);
        } else {
            $errors['dateFin'] = 'Date de fin invalide (format AAAA-MM-JJ).';
        }

        $statut = $data['statut'] ?? null;
        if (\in_array($statut, [Saison::STATUT_ACTIVE, Saison::STATUT_CLOTUREE], true)) {
            $entity->setStatut($statut);
        }

        return $errors;
    }

    protected function serialize(UserOwnedInterface $entity): array
    {
        \assert($entity instanceof Saison);

        return [
            'id' => $entity->getId(),
            'name' => $entity->getName(),
            'annee' => $entity->getAnnee(),
            'dateDebut' => $entity->getDateDebut()?->format('Y-m-d'),
            'dateFin' => $entity->getDateFin()?->format('Y-m-d'),
            'statut' => $entity->getStatut(),
        ];
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || '' === trim($value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', trim($value));

        return false !== $date ? $date->setTime(0, 0) : null;
    }

    #[Route('', name: 'api_saisons', methods: ['GET'])]
    public function list(#[CurrentUser] Utilisateur $user): JsonResponse
    {
        return $this->doList($user);
    }

    #[Route('/{id}', name: 'api_saison_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Saison $saison): JsonResponse
    {
        return $this->doShow($saison);
    }

    #[Route('', name: 'api_saison_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        // Une seule saison active par utilisateur : clôturer l'éventuelle active
        // avant de créer la nouvelle (report géométrie/pérennes = Phase 4).
        $active = $this->repo->findActiveForUser($user);
        if (null !== $active) {
            $active->setStatut(Saison::STATUT_CLOTUREE);
        }

        return $this->doCreate($request, $user);
    }

    #[Route('/new-cycle', name: 'api_saison_new_cycle', methods: ['POST'])]
    public function newCycle(Request $request, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        // Démarrer une nouvelle saison avec report : clôture l'active, crée la
        // nouvelle saison active et recopie la géométrie des bacs (cf. SaisonCycleService).
        $data = json_decode($request->getContent(), true) ?? [];
        $saison = $this->createEntity($user);
        \assert($saison instanceof Saison);

        $errors = $this->applyPayload($saison, $data, $user);
        if ([] !== $errors) {
            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->cycle->startNewSeason($user, $saison);

        return new JsonResponse(\App\Service\Utf8::clean($this->serialize($saison)), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_saison_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Request $request, Saison $saison, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        return $this->doUpdate($request, $saison, $user);
    }

    #[Route('/{id}', name: 'api_saison_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Saison $saison): JsonResponse
    {
        return $this->doDelete($saison);
    }

    #[Route('/{id}/cloturer', name: 'api_saison_cloturer', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function cloturer(Saison $saison): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $saison);

        $saison->setStatut(Saison::STATUT_CLOTUREE);
        $this->em->flush();

        return new JsonResponse(\App\Service\Utf8::clean($this->serialize($saison)));
    }
}
