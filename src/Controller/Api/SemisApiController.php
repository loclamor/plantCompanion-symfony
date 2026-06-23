<?php

namespace App\Controller\Api;

use App\Entity\GraineLot;
use App\Entity\Rempotage;
use App\Entity\Semis;
use App\Entity\Utilisateur;
use App\Exception\ClosedSeasonException;
use App\Repository\GraineLotRepository;
use App\Repository\GraineTypeRepository;
use App\Repository\RempotageRepository;
use App\Repository\SaisonRepository;
use App\Repository\SemisRepository;
use App\Security\Voter\OwnerVoter;
use App\Service\CurrentSeason;
use App\Service\SeasonGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * API JSON des semis (module Potager). Un semis = une graine suivie
 * individuellement, scopée à une saison, consommant un GraineLot. Premier
 * consommateur du SeasonGuard (écriture refusée sur une saison clôturée).
 */
#[Route('/api/semis')]
final class SemisApiController extends AbstractController
{
    private const METHODES = [Semis::METHODE_DIRECT, Semis::METHODE_GODET];
    private const STATUTS = [Semis::STATUT_SEME, Semis::STATUT_LEVE, Semis::STATUT_PLANTE, Semis::STATUT_ECHEC];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SemisRepository $semis,
        private readonly SaisonRepository $saisons,
        private readonly GraineTypeRepository $graineTypes,
        private readonly GraineLotRepository $graineLots,
        private readonly RempotageRepository $rempotages,
        private readonly CurrentSeason $currentSeason,
        private readonly SeasonGuard $seasonGuard,
    ) {
    }

    #[Route('', name: 'api_semis_index', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $saison = $this->resolveOwned($this->saisons, $request->query->get('saison'), $user)
            ?? $this->currentSeason->resolve($user);

        $statut = $request->query->getString('statut');
        $statut = \in_array($statut, self::STATUTS, true) ? $statut : null;

        $graineType = $this->resolveOwned($this->graineTypes, $request->query->get('graineType'), $user);

        $rows = $this->semis->findByUserFiltered($user, $saison, $statut, $graineType);

        return new JsonResponse(\App\Service\Utf8::clean([
            'saison' => $saison ? ['id' => $saison->getId(), 'name' => $saison->getName()] : null,
            'items' => array_map(fn (Semis $s) => $this->serialize($s), $rows),
        ]));
    }

    #[Route('/batch', name: 'api_semis_batch', methods: ['POST'])]
    public function createBatch(Request $request, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $saison = $this->resolveOwned($this->saisons, $data['saison'] ?? null, $user)
            ?? $this->currentSeason->resolve($user);
        if (null === $saison) {
            return new JsonResponse(['errors' => ['saison' => 'Aucune saison sélectionnée.']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (null !== $resp = $this->guard($saison)) {
            return $resp;
        }

        $entries = $data['entries'] ?? [];
        if (!\is_array($entries) || [] === $entries) {
            return new JsonResponse(['errors' => ['entries' => 'Au moins une ligne est requise.']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $prepared = [];
        $entryErrors = [];
        foreach ($entries as $i => $entry) {
            $errors = [];

            $graineType = $this->resolveOwned($this->graineTypes, $entry['graineType'] ?? null, $user);
            if (null === $graineType) {
                $errors['graineType'] = 'Type de graine invalide ou obligatoire.';
            }

            $methode = $entry['methode'] ?? null;
            if (!\in_array($methode, self::METHODES, true)) {
                $errors['methode'] = 'Méthode invalide.';
            }

            $dateSemis = $this->parseDate($entry['dateSemis'] ?? null);
            if (null === $dateSemis) {
                $errors['dateSemis'] = 'Date de semis invalide ou obligatoire.';
            }

            $quantite = (int) ($entry['quantite'] ?? 0);
            if ($quantite < 1) {
                $errors['quantite'] = 'Quantité invalide (≥ 1).';
            }

            $graineLot = $this->resolveOwned($this->graineLots, $entry['graineLot'] ?? null, $user);

            if ([] !== $errors) {
                $entryErrors[$i] = $errors;
                continue;
            }

            $prepared[] = [$graineType, $graineLot, $methode, $dateSemis, $quantite];
        }

        if ([] !== $entryErrors) {
            return new JsonResponse(['errors' => ['entries' => $entryErrors]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $created = [];
        foreach ($prepared as [$graineType, $graineLot, $methode, $dateSemis, $quantite]) {
            for ($n = 0; $n < $quantite; ++$n) {
                $s = (new Semis())
                    ->setUtilisateur($user)
                    ->setSaison($saison)
                    ->setGraineType($graineType)
                    ->setGraineLot($graineLot)
                    ->setMethode($methode)
                    ->setDateSemis($dateSemis);
                $s->recomputeStatut();
                $this->adjustLot($graineLot, -1);
                $this->em->persist($s);
                $created[] = $s;
            }
        }
        $this->em->flush();

        return new JsonResponse(\App\Service\Utf8::clean([
            'items' => array_map(fn (Semis $s) => $this->serialize($s), $created),
        ]), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_semis_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Semis $semis): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::VIEW, $semis);

        return new JsonResponse(\App\Service\Utf8::clean($this->serialize($semis)));
    }

    #[Route('', name: 'api_semis_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $semis = (new Semis())->setUtilisateur($user);

        $errors = $this->apply($semis, $data, $user);
        if ([] !== $errors) {
            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (null !== $resp = $this->guard($semis->getSaison())) {
            return $resp;
        }

        $this->adjustLot($semis->getGraineLot(), -1);
        $this->em->persist($semis);
        $this->em->flush();

        return new JsonResponse(\App\Service\Utf8::clean($this->serialize($semis)), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_semis_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Request $request, Semis $semis, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $semis);
        if (null !== $resp = $this->guard($semis->getSaison())) {
            return $resp;
        }

        $oldLot = $semis->getGraineLot();

        $data = json_decode($request->getContent(), true) ?? [];
        $errors = $this->apply($semis, $data, $user);
        if ([] !== $errors) {
            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        // La nouvelle saison (si changée) doit aussi être ouverte.
        if (null !== $resp = $this->guard($semis->getSaison())) {
            return $resp;
        }

        $newLot = $semis->getGraineLot();
        if ($oldLot !== $newLot) {
            $this->adjustLot($oldLot, 1);
            $this->adjustLot($newLot, -1);
        }

        $this->em->flush();

        return new JsonResponse(\App\Service\Utf8::clean($this->serialize($semis)));
    }

    #[Route('/{id}', name: 'api_semis_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Semis $semis): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::DELETE, $semis);
        if (null !== $resp = $this->guard($semis->getSaison())) {
            return $resp;
        }

        $this->adjustLot($semis->getGraineLot(), 1);
        $this->em->remove($semis);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/rempotages', name: 'api_semis_rempotage_add', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addRempotage(Request $request, Semis $semis, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $semis);
        if (null !== $resp = $this->guard($semis->getSaison())) {
            return $resp;
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $date = $this->parseDate($data['date'] ?? null);
        if (null === $date) {
            return new JsonResponse(['errors' => ['date' => 'Date de rempotage invalide ou obligatoire.']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $rempotage = (new Rempotage())
            ->setUtilisateur($user)
            ->setDate($date)
            ->setNotes($this->nullableString($data['notes'] ?? null));
        $semis->addRempotage($rempotage);
        $this->em->persist($rempotage);
        $this->em->flush();

        return new JsonResponse(\App\Service\Utf8::clean($this->serialize($semis)), Response::HTTP_CREATED);
    }

    #[Route('/{id}/rempotages/{rid}', name: 'api_semis_rempotage_delete', methods: ['DELETE'], requirements: ['id' => '\d+', 'rid' => '\d+'])]
    public function deleteRempotage(Semis $semis, int $rid): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $semis);
        if (null !== $resp = $this->guard($semis->getSaison())) {
            return $resp;
        }

        $rempotage = $this->rempotages->find($rid);
        if (null === $rempotage || $rempotage->getSemis() !== $semis) {
            return new JsonResponse(['message' => 'Rempotage introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $semis->removeRempotage($rempotage);
        $this->em->remove($rempotage);
        $this->em->flush();

        return new JsonResponse(\App\Service\Utf8::clean($this->serialize($semis)));
    }

    /**
     * Remplit un semis depuis le payload. Retourne les erreurs (vide si OK).
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    private function apply(Semis $semis, array $data, Utilisateur $user): array
    {
        $errors = [];

        // Saison : payload prioritaire ; sinon existante ; sinon saison courante.
        $saisonId = $data['saison'] ?? null;
        if (null !== $saisonId && '' !== $saisonId) {
            $saison = $this->resolveOwned($this->saisons, $saisonId, $user);
            if (null === $saison) {
                $errors['saison'] = 'Saison invalide.';
            } else {
                $semis->setSaison($saison);
            }
        } elseif (null === $semis->getSaison()) {
            $current = $this->currentSeason->resolve($user);
            if (null === $current) {
                $errors['saison'] = 'Aucune saison sélectionnée.';
            } else {
                $semis->setSaison($current);
            }
        }

        $graineType = $this->resolveOwned($this->graineTypes, $data['graineType'] ?? null, $user);
        if (null === $graineType) {
            $errors['graineType'] = 'Type de graine invalide ou obligatoire.';
        } else {
            $semis->setGraineType($graineType);
        }

        $semis->setGraineLot($this->resolveOwned($this->graineLots, $data['graineLot'] ?? null, $user));

        $methode = $data['methode'] ?? null;
        if (\in_array($methode, self::METHODES, true)) {
            $semis->setMethode($methode);
        } else {
            $errors['methode'] = 'Méthode invalide (direct ou godet).';
        }

        $dateSemis = $this->parseDate($data['dateSemis'] ?? null);
        if (null === $dateSemis) {
            $errors['dateSemis'] = 'Date de semis invalide ou obligatoire.';
        } else {
            $semis->setDateSemis($dateSemis);
        }

        $semis->setDateLevee($this->parseDate($data['dateLevee'] ?? null));
        $semis->setDatePlantation($this->parseDate($data['datePlantation'] ?? null));
        $semis->setDatePlantationTheorique($this->parseDate($data['datePlantationTheorique'] ?? null));
        $semis->setDateRecolteTheorique($this->parseDate($data['dateRecolteTheorique'] ?? null));
        $semis->setEchec((bool) ($data['echec'] ?? false));
        $semis->setNotes($this->nullableString($data['notes'] ?? null));

        $semis->recomputeStatut();

        return $errors;
    }

    /**
     * Garde « saison clôturée = lecture seule ». Renvoie une réponse 409 si
     * fermée, sinon null.
     */
    private function guard(?\App\Entity\Saison $saison): ?JsonResponse
    {
        if (null === $saison) {
            return null;
        }
        try {
            $this->seasonGuard->assertWritable($saison);
        } catch (ClosedSeasonException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return null;
    }

    private function adjustLot(?GraineLot $lot, int $delta): void
    {
        if (null === $lot) {
            return;
        }
        $lot->setQuantiteRestante(max(0, (int) $lot->getQuantiteRestante() + $delta));
    }

    private function resolveOwned(object $repository, mixed $id, Utilisateur $user): ?object
    {
        if (null === $id || '' === $id) {
            return null;
        }
        $entity = $repository->find((int) $id);

        return ($entity instanceof \App\Entity\UserOwnedInterface && $entity->getUtilisateur() === $user) ? $entity : null;
    }

    private function nullableString(mixed $v): ?string
    {
        $v = is_string($v) ? trim($v) : $v;

        return (null === $v || '' === $v) ? null : (string) $v;
    }

    private function parseDate(mixed $v): ?\DateTimeImmutable
    {
        if (!is_string($v) || '' === trim($v)) {
            return null;
        }

        try {
            return (new \DateTimeImmutable($v))->setTime(0, 0);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Semis $s): array
    {
        $lot = $s->getGraineLot();
        $graine = $lot?->getGraine();

        return [
            'id' => $s->getId(),
            'saison' => $s->getSaison() ? ['id' => $s->getSaison()->getId(), 'name' => $s->getSaison()->getName()] : null,
            'graineType' => $s->getGraineType() ? [
                'id' => $s->getGraineType()->getId(),
                'name' => $s->getGraineType()->getName(),
                'code' => $s->getGraineType()->getCode(),
            ] : null,
            'graineLot' => $lot ? [
                'id' => $lot->getId(),
                'quantiteRestante' => $lot->getQuantiteRestante(),
                'graine' => $graine ? ['id' => $graine->getId(), 'code' => $graine->getCode(), 'name' => $graine->getName()] : null,
            ] : null,
            'methode' => $s->getMethode(),
            'dateSemis' => $s->getDateSemis()?->format('Y-m-d'),
            'dateLevee' => $s->getDateLevee()?->format('Y-m-d'),
            'datePlantation' => $s->getDatePlantation()?->format('Y-m-d'),
            'datePlantationTheorique' => $s->getDatePlantationTheorique()?->format('Y-m-d'),
            'dateRecolteTheorique' => $s->getDateRecolteTheorique()?->format('Y-m-d'),
            'statut' => $s->getStatut(),
            'echec' => $s->isEchec(),
            'notes' => $s->getNotes(),
            'rempotages' => array_map(static fn (Rempotage $r) => [
                'id' => $r->getId(),
                'date' => $r->getDate()?->format('Y-m-d'),
                'notes' => $r->getNotes(),
            ], $s->getRempotages()->toArray()),
        ];
    }
}
