<?php

namespace App\Controller\Api;

use App\Entity\Utilisateur;
use App\Entity\Vegetable;
use App\Repository\ActionRepository;
use App\Repository\GroupRepository;
use App\Repository\LieuRepository;
use App\Repository\PhotoRepository;
use App\Repository\PorteGreffeRepository;
use App\Repository\TypeRepository;
use App\Repository\VegetableHistoryRepository;
use App\Repository\VegetableRepository;
use App\Security\Voter\OwnerVoter;
use App\Service\CurrentGroup;
use Doctrine\ORM\EntityManagerInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * API JSON des plantes pour le SPA Vue. Réutilise la logique existante :
 * VegetableRepository (filtres/pagination), OwnerVoter (accès), CurrentGroup.
 */
#[Route('/api/vegetables')]
final class VegetableApiController extends AbstractController
{
    private const PAGE_SIZE = 12;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VegetableRepository $vegetables,
        private readonly TypeRepository $types,
        private readonly GroupRepository $groups,
        private readonly PorteGreffeRepository $porteGreffes,
        private readonly LieuRepository $lieux,
        private readonly PhotoRepository $photos,
        private readonly CacheManager $imagine,
        private readonly \App\Service\PhotoPresenter $photoPresenter,
    ) {
    }

    #[Route('', name: 'api_vegetable_index', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] Utilisateur $user, CurrentGroup $currentGroup): JsonResponse
    {
        $q = trim($request->query->getString('q'));
        $sort = $request->query->getString('sort', 'name');
        $dir = $request->query->getString('dir', 'asc');

        $type = null;
        $typeId = $request->query->get('type');
        if (null !== $typeId && '' !== $typeId) {
            $type = $this->types->find((int) $typeId);
            if (null === $type || $type->getUtilisateur() !== $user) {
                $type = null;
            }
        }

        $group = $currentGroup->resolve($user);

        $total = $this->vegetables->countByUserFiltered($user, $group, $q, $type);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min(max(1, $request->query->getInt('page', 1)), $pages);

        $rows = $this->vegetables->findByUserFiltered(
            $user, $group, $q, $type, $sort, $dir, self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE,
        );

        return new JsonResponse([
            'items' => array_map(fn (Vegetable $v) => $this->listItem($v), $rows),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    #[Route('/names', name: 'api_vegetable_names', methods: ['GET'])]
    public function names(#[CurrentUser] Utilisateur $user): JsonResponse
    {
        $items = array_map(
            static fn (Vegetable $v) => ['id' => $v->getId(), 'name' => $v->getName()],
            $this->vegetables->findByUser($user),
        );

        return new JsonResponse(\App\Service\Utf8::clean(['items' => $items]));
    }

    #[Route('/{id}', name: 'api_vegetable_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Vegetable $vegetable, PhotoRepository $photos, ActionRepository $actions, VegetableHistoryRepository $histories): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::VIEW, $vegetable);

        return new JsonResponse($this->detail($vegetable, $photos, $actions, $histories));
    }

    #[Route('', name: 'api_vegetable_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] Utilisateur $user, PhotoRepository $photos, ActionRepository $actions, VegetableHistoryRepository $histories): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $vegetable = new Vegetable();
        $vegetable->setUtilisateur($user);

        $errors = $this->apply($vegetable, $data, $user, true);
        if ([] !== $errors) {
            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($vegetable);
        $this->em->flush();

        return new JsonResponse($this->detail($vegetable, $photos, $actions, $histories), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_vegetable_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Request $request, Vegetable $vegetable, #[CurrentUser] Utilisateur $user, PhotoRepository $photos, ActionRepository $actions, VegetableHistoryRepository $histories): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $vegetable);

        $data = json_decode($request->getContent(), true) ?? [];
        $errors = $this->apply($vegetable, $data, $user, false);
        if ([] !== $errors) {
            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->flush();

        return new JsonResponse($this->detail($vegetable, $photos, $actions, $histories));
    }

    #[Route('/{id}', name: 'api_vegetable_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Vegetable $vegetable): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::DELETE, $vegetable);

        $this->em->remove($vegetable);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Remplit l'entité depuis le payload. Retourne un tableau d'erreurs (vide si OK).
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    private function apply(Vegetable $vegetable, array $data, Utilisateur $user, bool $isNew): array
    {
        $errors = [];

        $name = trim((string) ($data['name'] ?? ''));
        if ('' === $name) {
            $errors['name'] = 'Le nom est obligatoire.';
        } else {
            $vegetable->setName($name);
        }

        // Type (obligatoire, possédé par l'utilisateur)
        $type = $this->resolveOwned($this->types, $data['type'] ?? null, $user);
        if (null === $type) {
            $errors['type'] = 'Type invalide ou obligatoire.';
        } else {
            $vegetable->setType($type);
        }

        // Groupe (optionnel, possédé) — « Sans groupe » du legacy.
        $vegetable->setGroup($this->resolveOwned($this->groups, $data['group'] ?? null, $user));

        // Relations optionnelles
        $vegetable->setParent($this->resolveOwned($this->vegetables, $data['parent'] ?? null, $user));
        $vegetable->setLieuOrigine($this->resolveOwned($this->lieux, $data['lieuOrigine'] ?? null, $user));

        // Porte-greffe : soit existant, soit création inline (« Nouveau… » du legacy :
        // nouveau porte-greffe nommé, rattaché au type sélectionné).
        if ('-new-' === ($data['porteGreffe'] ?? null)) {
            $newName = trim((string) ($data['newPorteGreffe'] ?? ''));
            if ('' !== $newName && null !== $type) {
                $pg = (new \App\Entity\PorteGreffe())->setName($newName)->setType($type)->setUtilisateur($user);
                $this->em->persist($pg);
                $vegetable->setPorteGreffe($pg);
            } else {
                $vegetable->setPorteGreffe(null);
            }
        } else {
            $vegetable->setPorteGreffe($this->resolveOwned($this->porteGreffes, $data['porteGreffe'] ?? null, $user));
        }

        // Champs scalaires
        $vegetable->setTypeOrigine($this->nullableString($data['typeOrigine'] ?? null));
        $vegetable->setNomLatin($this->nullableString($data['nomLatin'] ?? null));
        $vegetable->setRusticite($this->nullableInt($data['rusticite'] ?? null));
        $vegetable->setMoisFructiDebut($this->nullableInt($data['moisFructiDebut'] ?? null));
        $vegetable->setMoisFructiFin($this->nullableInt($data['moisFructiFin'] ?? null));
        $vegetable->setMoisFleurDebut($this->nullableInt($data['moisFleurDebut'] ?? null));
        $vegetable->setMoisFleurFin($this->nullableInt($data['moisFleurFin'] ?? null));
        $vegetable->setPFleur($this->nullableString($data['pFleur'] ?? null));
        $vegetable->setPFructi($this->nullableString($data['pFructi'] ?? null));

        // Dates (colonnes non nulles) : valeur fournie sinon maintenant à la création
        $creation = $this->parseDate($data['creationDate'] ?? null);
        if (null !== $creation) {
            $vegetable->setCreationDate($creation);
        } elseif ($isNew) {
            $vegetable->setCreationDate(new \DateTime());
        }

        $add = $this->parseDate($data['addDate'] ?? null);
        if (null !== $add) {
            $vegetable->setAddDate($add);
        } elseif ($isNew) {
            $vegetable->setAddDate(new \DateTime());
        }

        return $errors;
    }

    /**
     * Récupère une entité par id si elle appartient à l'utilisateur, sinon null.
     */
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

    private function nullableInt(mixed $v): ?int
    {
        return (null === $v || '' === $v) ? null : (int) $v;
    }

    private function parseDate(mixed $v): ?\DateTime
    {
        if (!is_string($v) || '' === $v) {
            return null;
        }

        try {
            return new \DateTime($v);
        } catch (\Exception) {
            return null;
        }
    }

    private function thumbUrl(?\App\Entity\Photo $photo): ?string
    {
        $path = $photo?->getRelativePath();

        return null !== $path ? $this->imagine->getBrowserPath($path, 'plant_thumb') : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function listItem(Vegetable $v): array
    {
        return \App\Service\Utf8::clean([
            'id' => $v->getId(),
            'name' => $v->getName(),
            'rusticite' => $v->getRusticite(),
            'type' => $v->getType() ? ['id' => $v->getType()->getId(), 'name' => $v->getType()->getName()] : null,
            'defaultPhotoUrl' => $this->thumbUrl($v->getDefaultPhoto()),
            'photoCount' => \count($this->photos->findByVegetable($v)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Vegetable $v, PhotoRepository $photos, ActionRepository $actions, VegetableHistoryRepository $histories): array
    {
        $rel = static fn ($e) => $e ? ['id' => $e->getId(), 'name' => $e->getName()] : null;

        $photoItems = $this->photoPresenter->presentMany($photos->findByVegetable($v), $v->getDefaultPhoto());

        $actionItems = array_map(static fn ($a) => [
            'id' => $a->getId(),
            'date' => $a->getDate()?->format(\DateTimeInterface::ATOM),
            'typeAction' => $a->getTypeAction(),
            'title' => $a->getTitle(),
            'comment' => $a->getComment(),
        ], $actions->findByVegetable($v));

        $historyItems = array_map(static fn ($h) => [
            'date' => $h->getDate()?->format(\DateTimeInterface::ATOM),
            'key' => $h->getKey(),
            'oldValue' => $h->getOldValue(),
            'newValue' => $h->getNewValue(),
        ], $histories->findByVegetable($v));

        return \App\Service\Utf8::clean([
            'id' => $v->getId(),
            'name' => $v->getName(),
            'nomLatin' => $v->getNomLatin(),
            'typeOrigine' => $v->getTypeOrigine(),
            'rusticite' => $v->getRusticite(),
            'moisFructiDebut' => $v->getMoisFructiDebut(),
            'moisFructiFin' => $v->getMoisFructiFin(),
            'moisFleurDebut' => $v->getMoisFleurDebut(),
            'moisFleurFin' => $v->getMoisFleurFin(),
            'pFleur' => $v->getPFleur(),
            'pFructi' => $v->getPFructi(),
            'creationDate' => $v->getCreationDate()?->format(\DateTimeInterface::ATOM),
            'addDate' => $v->getAddDate()?->format(\DateTimeInterface::ATOM),
            'type' => $rel($v->getType()),
            'group' => $rel($v->getGroup()),
            'parent' => $rel($v->getParent()),
            'porteGreffe' => $rel($v->getPorteGreffe()),
            'lieuOrigine' => $rel($v->getLieuOrigine()),
            'defaultPhotoUrl' => $this->thumbUrl($v->getDefaultPhoto()),
            'photos' => $photoItems,
            'actions' => $actionItems,
            'histories' => $historyItems,
        ]);
    }
}
