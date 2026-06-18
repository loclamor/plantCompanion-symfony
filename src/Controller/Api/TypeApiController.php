<?php

namespace App\Controller\Api;

use App\Entity\Type;
use App\Entity\UserOwnedInterface;
use App\Entity\Utilisateur;
use App\Repository\TypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/types')]
final class TypeApiController extends AbstractOwnedCrudApiController
{
    public function __construct(EntityManagerInterface $em, private readonly TypeRepository $repo)
    {
        parent::__construct($em);
    }

    protected function getRepository(): object
    {
        return $this->repo;
    }

    protected function createEntity(Utilisateur $user): UserOwnedInterface
    {
        return (new Type())->setUtilisateur($user);
    }

    protected function applyPayload(UserOwnedInterface $entity, array $data, Utilisateur $user): array
    {
        \assert($entity instanceof Type);
        $errors = [];
        if (null !== $err = $this->applyName($entity, $data)) {
            $errors['name'] = $err;
        }
        $entity->setParent($this->resolveOwned($this->repo, $data['parent'] ?? null, $user));

        return $errors;
    }

    protected function serialize(UserOwnedInterface $entity): array
    {
        \assert($entity instanceof Type);

        return [
            'id' => $entity->getId(),
            'name' => $entity->getName(),
            'parent' => $entity->getParent() ? ['id' => $entity->getParent()->getId(), 'name' => $entity->getParent()->getName()] : null,
        ];
    }

    #[Route('', name: 'api_types', methods: ['GET'])]
    public function list(#[CurrentUser] Utilisateur $user): JsonResponse
    {
        return $this->doList($user);
    }

    #[Route('/{id}', name: 'api_type_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Type $type): JsonResponse
    {
        return $this->doShow($type);
    }

    #[Route('', name: 'api_type_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        return $this->doCreate($request, $user);
    }

    #[Route('/{id}', name: 'api_type_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Request $request, Type $type, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        return $this->doUpdate($request, $type, $user);
    }

    #[Route('/{id}', name: 'api_type_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Type $type): JsonResponse
    {
        return $this->doDelete($type);
    }
}
