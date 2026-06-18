<?php

namespace App\Controller\Api;

use App\Entity\Utilisateur;
use App\Entity\Vegetable;
use App\Repository\VegetableRepository;
use App\Service\CurrentGroup;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Calendrier de floraison/récolte pour le SPA : par plante, les mois couverts.
 * Reprend le fallback période→mois du legacy (Model_Vegetable::PERIODES_MOIS)
 * quand les mois précis ne sont pas renseignés.
 */
#[Route('/api/calendar')]
final class CalendarApiController extends AbstractController
{
    /** @var array<string, array{int, int}> */
    private const PERIODES_MOIS = [
        'Printemps' => [3, 5],
        'Ete' => [6, 8],
        'Automne' => [9, 11],
        'Hivers' => [12, 2],
        '4 Saisons' => [8, 7],
        'Printemps - Automne' => [3, 5],
    ];

    public function __construct(
        private readonly VegetableRepository $vegetables,
        private readonly CurrentGroup $currentGroup,
    ) {
    }

    #[Route('/fructification', name: 'api_calendar_fructification', methods: ['GET'])]
    public function fructification(#[CurrentUser] Utilisateur $user): JsonResponse
    {
        $group = $this->currentGroup->resolve($user);
        $vegetables = $this->vegetables->findByUserFiltered($user, $group, null, null, 'name', 'asc', 1000, 0);

        $rows = array_map(fn (Vegetable $v) => [
            'vegetable' => ['id' => $v->getId(), 'name' => $v->getName()],
            'fleur' => $this->range($v->getMoisFleurDebut(), $v->getMoisFleurFin(), $v->getPFleur()),
            'fructi' => $this->range($v->getMoisFructiDebut(), $v->getMoisFructiFin(), $v->getPFructi()),
        ], $vegetables);

        return new JsonResponse(['rows' => $rows]);
    }

    /**
     * Mois couverts : mois précis si renseignés, sinon repli sur la période.
     *
     * @return int[]
     */
    private function range(?int $debut, ?int $fin, ?string $periode): array
    {
        if ((null === $debut || null === $fin) && null !== $periode && isset(self::PERIODES_MOIS[$periode])) {
            [$debut, $fin] = self::PERIODES_MOIS[$periode];
        }

        if (null === $debut || null === $fin || $debut < 1 || $debut > 12 || $fin < 1 || $fin > 12) {
            return [];
        }

        $months = [];
        $m = $debut;
        for ($i = 0; $i < 12; ++$i) {
            $months[] = $m;
            if ($m === $fin) {
                break;
            }
            $m = $m % 12 + 1;
        }

        return $months;
    }
}
