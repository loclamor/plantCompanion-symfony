<?php

namespace App\Controller\Api;

use App\Entity\Bac;
use App\Entity\UserOwnedInterface;
use App\Entity\Utilisateur;
use App\Repository\BacRepository;
use App\Repository\SaisonRepository;
use App\Security\Voter\OwnerVoter;
use App\Service\SaisonCycleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * API JSON des bacs (module Potager). Le bac est l'identité logique persistante
 * réutilisée d'une saison à l'autre ; il porte des valeurs par défaut (taille
 * physique + découpage) recopiées dans un BacSaison au démarrage d'une saison
 * (cf. SaisonCycleService). Archivable pour ne plus être reporté.
 */
#[Route('/api/bacs')]
final class BacApiController extends AbstractOwnedCrudApiController
{
    public function __construct(
        EntityManagerInterface $em,
        private readonly BacRepository $repo,
        private readonly SaisonRepository $saisons,
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
        return (new Bac())->setUtilisateur($user);
    }

    protected function applyPayload(UserOwnedInterface $entity, array $data, Utilisateur $user): array
    {
        \assert($entity instanceof Bac);
        $errors = [];

        if (null !== $err = $this->applyName($entity, $data)) {
            $errors['name'] = $err;
        }

        $largeur = $this->positiveInt($data['largeurDefaut'] ?? null);
        if (null === $largeur) {
            $errors['largeurDefaut'] = 'La largeur doit être un entier positif (cm).';
        } else {
            $entity->setLargeurDefaut($largeur);
        }

        $longueur = $this->positiveInt($data['longueurDefaut'] ?? null);
        if (null === $longueur) {
            $errors['longueurDefaut'] = 'La longueur doit être un entier positif (cm).';
        } else {
            $entity->setLongueurDefaut($longueur);
        }

        $lignes = $this->positiveInt($data['lignesDefaut'] ?? null);
        if (null === $lignes) {
            $errors['lignesDefaut'] = 'Le nombre de lignes doit être un entier ≥ 1.';
        } else {
            $entity->setLignesDefaut($lignes);
        }

        $colonnes = $this->positiveInt($data['colonnesDefaut'] ?? null);
        if (null === $colonnes) {
            $errors['colonnesDefaut'] = 'Le nombre de colonnes doit être un entier ≥ 1.';
        } else {
            $entity->setColonnesDefaut($colonnes);
        }

        if (\array_key_exists('archived', $data)) {
            $entity->setArchived((bool) $data['archived']);
        }

        return $errors;
    }

    protected function serialize(UserOwnedInterface $entity): array
    {
        \assert($entity instanceof Bac);

        return [
            'id' => $entity->getId(),
            'name' => $entity->getName(),
            'largeurDefaut' => $entity->getLargeurDefaut(),
            'longueurDefaut' => $entity->getLongueurDefaut(),
            'lignesDefaut' => $entity->getLignesDefaut(),
            'colonnesDefaut' => $entity->getColonnesDefaut(),
            'archived' => $entity->isArchived(),
        ];
    }

    /** Entier strictement positif, sinon null. */
    private function positiveInt(mixed $v): ?int
    {
        if (null === $v || '' === $v || !is_numeric($v)) {
            return null;
        }
        $n = (int) $v;

        return $n >= 1 ? $n : null;
    }

    #[Route('', name: 'api_bacs', methods: ['GET'])]
    public function list(#[CurrentUser] Utilisateur $user): JsonResponse
    {
        return $this->doList($user);
    }

    #[Route('/{id}', name: 'api_bac_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Bac $bac): JsonResponse
    {
        return $this->doShow($bac);
    }

    #[Route('', name: 'api_bac_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $bac = $this->createEntity($user);
        \assert($bac instanceof Bac);

        $errors = $this->applyPayload($bac, $data, $user);
        if ([] !== $errors) {
            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($bac);
        $this->em->flush();

        // Ajout par défaut à la saison active courante (snapshot), pour que le bac
        // apparaisse immédiatement dans la saison en cours. Les saisons clôturées
        // (passées) ne sont jamais touchées. Flush du bac d'abord : createSnapshot
        // requête le dernier snapshot du bac (entité avec identifiant requis).
        $active = $this->saisons->findActiveForUser($user);
        if (null !== $active && !$bac->isArchived()) {
            $this->cycle->createSnapshot($user, $bac, $active);
            $this->em->flush();
        }

        return new JsonResponse(\App\Service\Utf8::clean($this->serialize($bac)), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_bac_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Request $request, Bac $bac, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        return $this->doUpdate($request, $bac, $user);
    }

    #[Route('/{id}', name: 'api_bac_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Bac $bac): JsonResponse
    {
        return $this->doDelete($bac);
    }

    #[Route('/{id}/archiver', name: 'api_bac_archiver', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function archiver(Request $request, Bac $bac): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $bac);

        $data = json_decode($request->getContent(), true) ?? [];
        // Défaut : archiver. Passer {"archived": false} pour désarchiver.
        $bac->setArchived((bool) ($data['archived'] ?? true));
        $this->em->flush();

        return new JsonResponse(\App\Service\Utf8::clean($this->serialize($bac)));
    }
}
