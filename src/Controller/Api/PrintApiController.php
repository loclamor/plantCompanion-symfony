<?php

namespace App\Controller\Api;

use App\Entity\Utilisateur;
use App\Entity\Vegetable;
use App\Repository\VegetableRepository;
use App\Service\Utf8;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Données d'étiquettes pour l'impression : toutes les plantes de l'utilisateur,
 * le front laisse choisir lesquelles imprimer.
 */
#[Route('/api/print')]
final class PrintApiController extends AbstractController
{
    public function __construct(
        private readonly VegetableRepository $vegetables,
    ) {
    }

    #[Route('/labels', name: 'api_print_labels', methods: ['GET'])]
    public function labels(#[CurrentUser] Utilisateur $user): JsonResponse
    {
        $items = array_map(static fn (Vegetable $v) => [
            'id' => $v->getId(),
            'name' => $v->getName(),
            'rusticite' => $v->getRusticite(),
            'type' => $v->getType() ? ['id' => $v->getType()->getId(), 'name' => $v->getType()->getName()] : null,
            'porteGreffe' => $v->getPorteGreffe()?->getName(),
            'moisFleurDebut' => $v->getMoisFleurDebut(),
            'moisFleurFin' => $v->getMoisFleurFin(),
            'pFleur' => $v->getPFleur(),
            'moisFructiDebut' => $v->getMoisFructiDebut(),
            'moisFructiFin' => $v->getMoisFructiFin(),
            'pFructi' => $v->getPFructi(),
        ], $this->vegetables->findByUser($user));

        return new JsonResponse(Utf8::clean(['items' => $items]));
    }
}
