<?php

namespace App\Controller\Api;

use App\Entity\Utilisateur;
use App\Repository\GroupRepository;
use App\Repository\LieuRepository;
use App\Repository\PorteGreffeRepository;
use App\Repository\TypeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Listes de référence (id + nom) possédées par l'utilisateur, pour alimenter
 * les selects du front. Lecture seule.
 */
#[Route('/api')]
final class ReferenceApiController extends AbstractController
{
    /** @param iterable<object> $entities */
    private function idName(iterable $entities): JsonResponse
    {
        $items = [];
        foreach ($entities as $e) {
            $items[] = ['id' => $e->getId(), 'name' => $e->getName()];
        }

        return new JsonResponse(['items' => $items]);
    }

    #[Route('/types', name: 'api_types', methods: ['GET'])]
    public function types(#[CurrentUser] Utilisateur $user, TypeRepository $repo): JsonResponse
    {
        return $this->idName($repo->findByUser($user));
    }

    #[Route('/groups', name: 'api_groups', methods: ['GET'])]
    public function groups(#[CurrentUser] Utilisateur $user, GroupRepository $repo): JsonResponse
    {
        return $this->idName($repo->findByUser($user));
    }

    #[Route('/lieux', name: 'api_lieux', methods: ['GET'])]
    public function lieux(#[CurrentUser] Utilisateur $user, LieuRepository $repo): JsonResponse
    {
        return $this->idName($repo->findByUser($user));
    }

    #[Route('/porte-greffes', name: 'api_porte_greffes', methods: ['GET'])]
    public function porteGreffes(#[CurrentUser] Utilisateur $user, PorteGreffeRepository $repo): JsonResponse
    {
        return $this->idName($repo->findByUser($user));
    }
}
