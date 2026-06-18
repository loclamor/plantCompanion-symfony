<?php

namespace App\Controller\Api;

use App\Entity\Lieu;
use App\Entity\UserOwnedInterface;
use App\Entity\Utilisateur;
use App\Repository\LieuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/lieux')]
final class LieuApiController extends AbstractOwnedCrudApiController
{
    public function __construct(EntityManagerInterface $em, private readonly LieuRepository $repo)
    {
        parent::__construct($em);
    }

    protected function getRepository(): object
    {
        return $this->repo;
    }

    protected function createEntity(Utilisateur $user): UserOwnedInterface
    {
        return (new Lieu())->setUtilisateur($user);
    }

    protected function applyPayload(UserOwnedInterface $entity, array $data, Utilisateur $user): array
    {
        \assert($entity instanceof Lieu);
        $errors = [];
        if (null !== $err = $this->applyName($entity, $data)) {
            $errors['name'] = $err;
        }

        return $errors;
    }

    protected function serialize(UserOwnedInterface $entity): array
    {
        \assert($entity instanceof Lieu);

        return ['id' => $entity->getId(), 'name' => $entity->getName()];
    }

    #[Route('', name: 'api_lieux', methods: ['GET'])]
    public function list(#[CurrentUser] Utilisateur $user): JsonResponse
    {
        return $this->doList($user);
    }

    #[Route('/{id}', name: 'api_lieu_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Lieu $lieu): JsonResponse
    {
        return $this->doShow($lieu);
    }

    #[Route('', name: 'api_lieu_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        return $this->doCreate($request, $user);
    }

    #[Route('/{id}', name: 'api_lieu_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Request $request, Lieu $lieu, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        return $this->doUpdate($request, $lieu, $user);
    }

    #[Route('/{id}', name: 'api_lieu_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Lieu $lieu): JsonResponse
    {
        return $this->doDelete($lieu);
    }
}
