<?php

namespace App\Controller\Api;

use App\Entity\GraineType;
use App\Entity\UserOwnedInterface;
use App\Entity\Utilisateur;
use App\Repository\GraineRepository;
use App\Repository\GraineTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/graine-types')]
final class GraineTypeApiController extends AbstractOwnedCrudApiController
{
    public function __construct(
        EntityManagerInterface $em,
        private readonly GraineTypeRepository $repo,
        private readonly GraineRepository $graines,
    ) {
        parent::__construct($em);
    }

    protected function getRepository(): object
    {
        return $this->repo;
    }

    protected function createEntity(Utilisateur $user): UserOwnedInterface
    {
        return (new GraineType())->setUtilisateur($user);
    }

    protected function applyPayload(UserOwnedInterface $entity, array $data, Utilisateur $user): array
    {
        \assert($entity instanceof GraineType);
        $errors = [];

        if (null !== $err = $this->applyName($entity, $data)) {
            $errors['name'] = $err;
        }

        // Préfixe de code obligatoire et unique par utilisateur.
        $code = trim((string) ($data['code'] ?? ''));
        if ('' === $code) {
            $errors['code'] = 'Le préfixe de code est obligatoire.';
        } elseif (null !== $this->repo->findOneByCode($user, $code, $entity->getId())) {
            $errors['code'] = 'Ce préfixe est déjà utilisé.';
        } else {
            $entity->setCode($code);
        }

        // Type parent (optionnel, possédé) ; refus des cycles.
        $parentId = $data['parent'] ?? null;
        if (null === $parentId || '' === $parentId) {
            $entity->setParent(null);
        } else {
            $parent = $this->resolveOwned($this->repo, $parentId, $user);
            if (null === $parent) {
                $errors['parent'] = 'Type parent invalide.';
            } elseif ($parent === $entity
                || (null !== $entity->getId() && \in_array($parent->getId(), $this->repo->descendantIds($user, $entity), true))) {
                $errors['parent'] = 'Un type ne peut pas être son propre parent ni descendant.';
            } else {
                $entity->setParent($parent);
            }
        }

        return $errors;
    }

    protected function serialize(UserOwnedInterface $entity): array
    {
        \assert($entity instanceof GraineType);

        $nbGraines = null !== $entity->getId()
            ? \count($this->graines->findByUserAndGraineType($entity->getUtilisateur(), $entity))
            : 0;

        return [
            'id' => $entity->getId(),
            'name' => $entity->getName(),
            'code' => $entity->getCode(),
            'parentId' => $entity->getParent()?->getId(),
            'parentName' => $entity->getParent()?->getName(),
            'nbGraines' => $nbGraines,
        ];
    }

    #[Route('', name: 'api_graine_types', methods: ['GET'])]
    public function list(#[CurrentUser] Utilisateur $user): JsonResponse
    {
        return $this->doList($user);
    }

    #[Route('/{id}', name: 'api_graine_type_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(GraineType $graineType): JsonResponse
    {
        return $this->doShow($graineType);
    }

    #[Route('', name: 'api_graine_type_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        return $this->doCreate($request, $user);
    }

    #[Route('/{id}', name: 'api_graine_type_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Request $request, GraineType $graineType, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        return $this->doUpdate($request, $graineType, $user);
    }

    #[Route('/{id}', name: 'api_graine_type_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(GraineType $graineType): JsonResponse
    {
        return $this->doDelete($graineType);
    }
}
