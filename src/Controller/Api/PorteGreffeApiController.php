<?php

namespace App\Controller\Api;

use App\Entity\PorteGreffe;
use App\Entity\UserOwnedInterface;
use App\Entity\Utilisateur;
use App\Repository\PorteGreffeRepository;
use App\Repository\TypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/porte-greffes')]
final class PorteGreffeApiController extends AbstractOwnedCrudApiController
{
    public function __construct(
        EntityManagerInterface $em,
        private readonly PorteGreffeRepository $repo,
        private readonly TypeRepository $types,
    ) {
        parent::__construct($em);
    }

    protected function getRepository(): object
    {
        return $this->repo;
    }

    protected function createEntity(Utilisateur $user): UserOwnedInterface
    {
        return (new PorteGreffe())->setUtilisateur($user);
    }

    protected function applyPayload(UserOwnedInterface $entity, array $data, Utilisateur $user): array
    {
        \assert($entity instanceof PorteGreffe);
        $errors = [];
        if (null !== $err = $this->applyName($entity, $data)) {
            $errors['name'] = $err;
        }

        // Type obligatoire et possédé par l'utilisateur.
        $type = $this->resolveOwned($this->types, $data['type'] ?? null, $user);
        if (null === $type) {
            $errors['type'] = 'Le type est obligatoire.';
        } else {
            $entity->setType($type);
        }

        return $errors;
    }

    protected function serialize(UserOwnedInterface $entity): array
    {
        \assert($entity instanceof PorteGreffe);

        return [
            'id' => $entity->getId(),
            'name' => $entity->getName(),
            'type' => $entity->getType() ? ['id' => $entity->getType()->getId(), 'name' => $entity->getType()->getName()] : null,
        ];
    }

    #[Route('', name: 'api_porte_greffes', methods: ['GET'])]
    public function list(#[CurrentUser] Utilisateur $user): JsonResponse
    {
        return $this->doList($user);
    }

    #[Route('/{id}', name: 'api_porte_greffe_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(PorteGreffe $porteGreffe): JsonResponse
    {
        return $this->doShow($porteGreffe);
    }

    #[Route('', name: 'api_porte_greffe_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        return $this->doCreate($request, $user);
    }

    #[Route('/{id}', name: 'api_porte_greffe_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Request $request, PorteGreffe $porteGreffe, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        return $this->doUpdate($request, $porteGreffe, $user);
    }

    #[Route('/{id}', name: 'api_porte_greffe_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(PorteGreffe $porteGreffe): JsonResponse
    {
        return $this->doDelete($porteGreffe);
    }
}
