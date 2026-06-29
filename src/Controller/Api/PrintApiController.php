<?php

namespace App\Controller\Api;

use App\Entity\Culture;
use App\Entity\Semis;
use App\Entity\Utilisateur;
use App\Entity\Vegetable;
use App\Repository\CultureRepository;
use App\Repository\SemisRepository;
use App\Repository\VegetableRepository;
use App\Service\CurrentSeason;
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
        private readonly SemisRepository $semis,
        private readonly CultureRepository $cultures,
        private readonly CurrentSeason $currentSeason,
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

    /**
     * Étiquettes du potager pour la saison courante : un item par semis vivant
     * et par culture plantée en direct (sans semis d'origine), regroupés par
     * (code, nom) avec le nombre d'occurrences réelles.
     */
    #[Route('/cultures', name: 'api_print_cultures', methods: ['GET'])]
    public function cultures(#[CurrentUser] Utilisateur $user): JsonResponse
    {
        $saison = $this->currentSeason->resolve($user);
        if (null === $saison) {
            return new JsonResponse(['items' => []]);
        }

        /** @var array<string, array{code: ?string, name: string, count: int}> $groups */
        $groups = [];
        $add = static function (?string $code, ?string $name) use (&$groups): void {
            $name = (string) $name;
            if ('' === $name && null === $code) {
                return;
            }
            $key = ($code ?? '')."\x00".$name;
            if (!isset($groups[$key])) {
                $groups[$key] = ['code' => $code, 'name' => $name, 'count' => 0];
            }
            ++$groups[$key]['count'];
        };

        // Semis vivants (échec exclu) : code/nom depuis la graine, sinon le type.
        foreach ($this->semis->findByUserFiltered($user, $saison, null, null) as $s) {
            /** @var Semis $s */
            if ($s->isEchec()) {
                continue;
            }
            $graine = $s->getGraineLot()?->getGraine();
            $code = $graine?->getCode() ?? $s->getGraineType()?->getCode();
            $name = $graine?->getName() ?? $s->getGraineType()?->getName();
            $add($code, $name);
        }

        // Cultures en place plantées en direct (sans semis d'origine).
        foreach ($this->cultures->findByUserFiltered($user, $saison, null, Culture::STATUT_EN_PLACE) as $c) {
            /** @var Culture $c */
            if (null !== $c->getSemis()) {
                continue;
            }
            $add($c->getGraineType()?->getCode(), $c->getName());
        }

        $items = array_values($groups);
        usort($items, static fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return new JsonResponse(Utf8::clean(['items' => $items]));
    }
}
