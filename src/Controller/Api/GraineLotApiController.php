<?php

namespace App\Controller\Api;

use App\Entity\GraineLot;
use App\Entity\UserOwnedInterface;
use App\Entity\Utilisateur;
use App\Repository\GraineLotRepository;
use App\Repository\GraineRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/graine-lots')]
final class GraineLotApiController extends AbstractOwnedCrudApiController
{
    private const SOURCES = ['achat', 'recolte'];

    public function __construct(
        EntityManagerInterface $em,
        private readonly GraineLotRepository $repo,
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
        return (new GraineLot())->setUtilisateur($user);
    }

    protected function applyPayload(UserOwnedInterface $entity, array $data, Utilisateur $user): array
    {
        \assert($entity instanceof GraineLot);
        $errors = [];
        $isNew = null === $entity->getId();

        // Graine rattachée (obligatoire, possédée).
        $graine = $this->resolveOwned($this->graines, $data['graine'] ?? null, $user);
        if (null === $graine) {
            $errors['graine'] = 'Graine invalide ou obligatoire.';
        } else {
            $entity->setGraine($graine);
        }

        // Source (achat | recolte).
        $source = $data['source'] ?? null;
        if (\in_array($source, self::SOURCES, true)) {
            $entity->setSource($source);
        } else {
            $errors['source'] = 'Source invalide (achat ou recolte).';
        }

        // Date d'acquisition (obligatoire).
        $date = $this->parseDate($data['dateAcquisition'] ?? null);
        if (null === $date) {
            $errors['dateAcquisition'] = 'Date d\'acquisition invalide ou obligatoire.';
        } else {
            $entity->setDateAcquisition($date);
        }

        // Quantités.
        $initiale = $data['quantiteInitiale'] ?? null;
        if (null === $initiale || '' === $initiale || (int) $initiale < 0) {
            $errors['quantiteInitiale'] = 'Quantité initiale invalide.';
        } else {
            $entity->setQuantiteInitiale((int) $initiale);
        }

        // Quantité restante : valeur fournie, sinon = initiale à la création.
        $restante = $data['quantiteRestante'] ?? null;
        if (null !== $restante && '' !== $restante) {
            $entity->setQuantiteRestante(max(0, (int) $restante));
        } elseif ($isNew && !isset($errors['quantiteInitiale'])) {
            $entity->setQuantiteRestante((int) $initiale);
        }

        $fournisseur = trim((string) ($data['fournisseur'] ?? ''));
        $entity->setFournisseur('' === $fournisseur ? null : $fournisseur);

        $notes = trim((string) ($data['notes'] ?? ''));
        $entity->setNotes('' === $notes ? null : $notes);

        return $errors;
    }

    protected function serialize(UserOwnedInterface $entity): array
    {
        \assert($entity instanceof GraineLot);
        $graine = $entity->getGraine();

        return [
            'id' => $entity->getId(),
            'graine' => $graine ? ['id' => $graine->getId(), 'code' => $graine->getCode(), 'name' => $graine->getName()] : null,
            'source' => $entity->getSource(),
            'dateAcquisition' => $entity->getDateAcquisition()?->format('Y-m-d'),
            'quantiteInitiale' => $entity->getQuantiteInitiale(),
            'quantiteRestante' => $entity->getQuantiteRestante(),
            'fournisseur' => $entity->getFournisseur(),
            'notes' => $entity->getNotes(),
        ];
    }

    private function parseDate(mixed $v): ?\DateTime
    {
        if (!\is_string($v) || '' === $v) {
            return null;
        }

        try {
            return new \DateTime($v);
        } catch (\Exception) {
            return null;
        }
    }

    #[Route('', name: 'api_graine_lots', methods: ['GET'])]
    public function list(Request $request, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $graineId = $request->query->get('graine');
        if (null !== $graineId && '' !== $graineId) {
            $graine = $this->resolveOwned($this->graines, $graineId, $user);
            if (null === $graine) {
                return new JsonResponse(['items' => []]);
            }

            return new JsonResponse(\App\Service\Utf8::clean([
                'items' => array_map(fn ($l) => $this->serialize($l), $this->repo->findByUserAndGraine($user, $graine)),
            ]));
        }

        return $this->doList($user);
    }

    #[Route('/{id}', name: 'api_graine_lot_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(GraineLot $graineLot): JsonResponse
    {
        return $this->doShow($graineLot);
    }

    #[Route('', name: 'api_graine_lot_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        return $this->doCreate($request, $user);
    }

    #[Route('/{id}', name: 'api_graine_lot_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Request $request, GraineLot $graineLot, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        return $this->doUpdate($request, $graineLot, $user);
    }

    #[Route('/{id}', name: 'api_graine_lot_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(GraineLot $graineLot): JsonResponse
    {
        return $this->doDelete($graineLot);
    }
}
