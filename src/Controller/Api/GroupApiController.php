<?php

namespace App\Controller\Api;

use App\Entity\Group;
use App\Entity\UserOwnedInterface;
use App\Entity\Utilisateur;
use App\Repository\GroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/groups')]
final class GroupApiController extends AbstractOwnedCrudApiController
{
    public function __construct(EntityManagerInterface $em, private readonly GroupRepository $repo)
    {
        parent::__construct($em);
    }

    protected function getRepository(): object
    {
        return $this->repo;
    }

    protected function createEntity(Utilisateur $user): UserOwnedInterface
    {
        return (new Group())->setUtilisateur($user);
    }

    protected function applyPayload(UserOwnedInterface $entity, array $data, Utilisateur $user): array
    {
        \assert($entity instanceof Group);
        $errors = [];
        if (null !== $err = $this->applyName($entity, $data)) {
            $errors['name'] = $err;
        }
        $entity->setParent($this->resolveOwned($this->repo, $data['parent'] ?? null, $user));

        return $errors;
    }

    protected function serialize(UserOwnedInterface $entity): array
    {
        \assert($entity instanceof Group);

        return [
            'id' => $entity->getId(),
            'name' => $entity->getName(),
            'parent' => $entity->getParent() ? ['id' => $entity->getParent()->getId(), 'name' => $entity->getParent()->getName()] : null,
        ];
    }

    #[Route('', name: 'api_groups', methods: ['GET'])]
    public function list(#[CurrentUser] Utilisateur $user): JsonResponse
    {
        return $this->doList($user);
    }

    #[Route('/{id}', name: 'api_group_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Group $group): JsonResponse
    {
        return $this->doShow($group);
    }

    #[Route('', name: 'api_group_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        return $this->doCreate($request, $user);
    }

    #[Route('/{id}', name: 'api_group_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Request $request, Group $group, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        return $this->doUpdate($request, $group, $user);
    }

    #[Route('/{id}', name: 'api_group_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Group $group): JsonResponse
    {
        return $this->doDelete($group);
    }
}
