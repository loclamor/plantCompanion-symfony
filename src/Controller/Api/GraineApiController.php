<?php

namespace App\Controller\Api;

use App\Entity\Graine;
use App\Entity\UserOwnedInterface;
use App\Entity\Utilisateur;
use App\Repository\GraineLotRepository;
use App\Repository\GraineRepository;
use App\Repository\GraineTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/graines')]
final class GraineApiController extends AbstractOwnedCrudApiController
{
    private const METHODES = ['pleine_terre', 'couvert'];

    public function __construct(
        EntityManagerInterface $em,
        private readonly GraineRepository $repo,
        private readonly GraineTypeRepository $graineTypes,
        private readonly GraineLotRepository $lots,
    ) {
        parent::__construct($em);
    }

    protected function getRepository(): object
    {
        return $this->repo;
    }

    protected function createEntity(Utilisateur $user): UserOwnedInterface
    {
        return (new Graine())->setUtilisateur($user);
    }

    protected function applyPayload(UserOwnedInterface $entity, array $data, Utilisateur $user): array
    {
        \assert($entity instanceof Graine);
        $errors = [];

        // Type de graine (obligatoire, possédé).
        $graineType = $this->resolveOwned($this->graineTypes, $data['graineType'] ?? null, $user);
        if (null === $graineType) {
            $errors['graineType'] = 'Type de graine invalide ou obligatoire.';
        } else {
            $entity->setGraineType($graineType);
        }

        if (null !== $err = $this->applyName($entity, $data)) {
            $errors['name'] = $err;
        }

        // Code : si vide, généré depuis le préfixe du type ; unique par utilisateur.
        $code = trim((string) ($data['code'] ?? ''));
        if ('' === $code && null !== $graineType) {
            $code = $this->repo->nextCodeForType($user, $graineType);
        }
        if ('' === $code) {
            $errors['code'] = 'Le code est obligatoire.';
        } elseif (null !== $this->repo->findOneByCode($user, $code, $entity->getId())) {
            $errors['code'] = 'Ce code est déjà utilisé.';
        } else {
            $entity->setCode($code);
        }

        // Méthode de semis conseillée (optionnelle, valeur contrôlée).
        $methode = $data['methodeSemisConseillee'] ?? null;
        if (null === $methode || '' === $methode) {
            $entity->setMethodeSemisConseillee(null);
        } elseif (\in_array($methode, self::METHODES, true)) {
            $entity->setMethodeSemisConseillee($methode);
        } else {
            $errors['methodeSemisConseillee'] = 'Méthode de semis conseillée invalide.';
        }

        $entity->setMoisSemis($this->month($data['moisSemis'] ?? null));
        $entity->setMoisPlantationTheorique($this->month($data['moisPlantationTheorique'] ?? null));
        $entity->setMoisRecolteTheorique($this->month($data['moisRecolteTheorique'] ?? null));

        $notes = trim((string) ($data['notes'] ?? ''));
        $entity->setNotes('' === $notes ? null : $notes);

        return $errors;
    }

    protected function serialize(UserOwnedInterface $entity): array
    {
        \assert($entity instanceof Graine);

        $lots = null !== $entity->getId()
            ? $this->lots->findByUserAndGraine($entity->getUtilisateur(), $entity)
            : [];
        $stock = array_sum(array_map(fn ($l) => $l->getQuantiteRestante(), $lots));
        $gt = $entity->getGraineType();

        return [
            'id' => $entity->getId(),
            'code' => $entity->getCode(),
            'name' => $entity->getName(),
            'graineType' => $gt ? ['id' => $gt->getId(), 'name' => $gt->getName(), 'code' => $gt->getCode()] : null,
            'methodeSemisConseillee' => $entity->getMethodeSemisConseillee(),
            'moisSemis' => $entity->getMoisSemis(),
            'moisPlantationTheorique' => $entity->getMoisPlantationTheorique(),
            'moisRecolteTheorique' => $entity->getMoisRecolteTheorique(),
            'notes' => $entity->getNotes(),
            'nbLots' => \count($lots),
            'stockRestant' => $stock,
        ];
    }

    /** Mois valide (1–12) ou null. */
    private function month(mixed $v): ?int
    {
        if (null === $v || '' === $v) {
            return null;
        }
        $n = (int) $v;

        return ($n >= 1 && $n <= 12) ? $n : null;
    }

    private const PAGE_SIZE = 20;

    #[Route('', name: 'api_graines', methods: ['GET'])]
    public function list(Request $request, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $q = trim($request->query->getString('q'));
        $sort = $request->query->getString('sort', 'code');
        $dir = $request->query->getString('dir', 'asc');
        $stockStatus = $request->query->getString('stock'); // '', rachat, faible, ok

        // Type (optionnel, possédé) ; un id non possédé est simplement ignoré.
        // Le filtre inclut le type ET tous ses descendants (hiérarchie).
        $graineTypeIds = null;
        $graineTypeId = $request->query->get('graineType');
        if (null !== $graineTypeId && '' !== $graineTypeId) {
            $graineType = $this->resolveOwned($this->graineTypes, $graineTypeId, $user);
            if (null !== $graineType) {
                $graineTypeIds = $this->graineTypes->descendantIds($user, $graineType);
            }
        }

        $total = $this->repo->countByUserFiltered($user, $q, $graineTypeIds, $stockStatus);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min(max(1, $request->query->getInt('page', 1)), $pages);

        $rows = $this->repo->findByUserFiltered(
            $user, $q, $graineTypeIds, $stockStatus, $sort, $dir,
            self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE,
        );

        return new JsonResponse(\App\Service\Utf8::clean([
            'items' => array_map(fn (Graine $g) => $this->serialize($g), $rows),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ]));
    }

    #[Route('/next-code', name: 'api_graine_next_code', methods: ['GET'])]
    public function nextCode(Request $request, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $graineType = $this->resolveOwned($this->graineTypes, $request->query->get('graineType'), $user);
        if (null === $graineType) {
            return new JsonResponse(['code' => '']);
        }

        return new JsonResponse(['code' => $this->repo->nextCodeForType($user, $graineType)]);
    }

    #[Route('/{id}', name: 'api_graine_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Graine $graine): JsonResponse
    {
        return $this->doShow($graine);
    }

    #[Route('', name: 'api_graine_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        return $this->doCreate($request, $user);
    }

    #[Route('/{id}', name: 'api_graine_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Request $request, Graine $graine, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        return $this->doUpdate($request, $graine, $user);
    }

    #[Route('/{id}', name: 'api_graine_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Graine $graine): JsonResponse
    {
        return $this->doDelete($graine);
    }
}
