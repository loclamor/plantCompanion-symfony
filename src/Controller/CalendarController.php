<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\VegetableRepository;
use App\Service\CurrentGroup;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/calendar')]
final class CalendarController extends AbstractController
{
    #[Route('/fructification', name: 'app_calendar_fructification', methods: ['GET'])]
    public function fructification(VegetableRepository $vegetableRepository, CurrentGroup $currentGroup): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();
        $group = $currentGroup->resolve($user);

        $vegetables = $vegetableRepository->findByUserFiltered($user, $group, null, null, 'name', 'asc', 1000, 0);

        $rows = [];
        foreach ($vegetables as $vegetable) {
            $rows[] = [
                'vegetable' => $vegetable,
                'fleur' => $this->monthRange($vegetable->getMoisFleurDebut(), $vegetable->getMoisFleurFin()),
                'fructi' => $this->monthRange($vegetable->getMoisFructiDebut(), $vegetable->getMoisFructiFin()),
            ];
        }

        return $this->render('calendar/fructification.html.twig', [
            'rows' => $rows,
            'months' => range(1, 12),
            'current_group' => $group,
        ]);
    }

    /**
     * Liste des mois couverts par un intervalle [début, fin], avec passage d'année.
     *
     * @return int[]
     */
    private function monthRange(?int $debut, ?int $fin): array
    {
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
