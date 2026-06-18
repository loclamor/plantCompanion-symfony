<?php

namespace App\Controller\Api;

use App\Entity\Action;
use App\Entity\Utilisateur;
use App\Repository\ActionRepository;
use App\Repository\VegetableRepository;
use App\Security\Voter\OwnerVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * API JSON des interventions (actions) liées aux plantes pour le SPA.
 * Réutilise OwnerVoter, ActionRepository, VegetableRepository.
 */
#[Route('/api')]
final class ActionApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ActionRepository $actions,
        private readonly VegetableRepository $vegetables,
    ) {
    }

    /** @return array<string, mixed> */
    private function serialize(Action $a): array
    {
        $v = $a->getVegetable();

        return \App\Service\Utf8::clean([
            'id' => $a->getId(),
            'date' => $a->getDate()?->format(\DateTimeInterface::ATOM),
            'typeAction' => $a->getTypeAction(),
            'title' => $a->getTitle(),
            'comment' => $a->getComment(),
            'vegetable' => $v ? ['id' => $v->getId(), 'name' => $v->getName()] : null,
        ]);
    }

    #[Route('/action-types', name: 'api_action_types', methods: ['GET'])]
    public function actionTypes(): JsonResponse
    {
        return new JsonResponse([
            'typesAction' => Action::TYPES_ACTION,
            'titresObservation' => Action::TITRES_OBSERVATION,
        ]);
    }

    #[Route('/actions', name: 'api_actions', methods: ['GET'])]
    public function index(#[CurrentUser] Utilisateur $user): JsonResponse
    {
        return new JsonResponse([
            'items' => array_map(fn (Action $a) => $this->serialize($a), $this->actions->findByUser($user)),
        ]);
    }

    #[Route('/actions/{id}', name: 'api_action_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Action $action): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::VIEW, $action);

        return new JsonResponse($this->serialize($action));
    }

    #[Route('/actions', name: 'api_action_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $action = new Action();
        $action->setUtilisateur($user);

        $errors = $this->apply($action, json_decode($request->getContent(), true) ?? [], $user);
        if ([] !== $errors) {
            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($action);
        $this->em->flush();

        return new JsonResponse($this->serialize($action), Response::HTTP_CREATED);
    }

    /**
     * Ajout multiple : une même intervention appliquée à plusieurs plantes
     * (reprend le principe du mode « groupe » legacy, sans photos).
     */
    #[Route('/actions/bulk', name: 'api_action_bulk', methods: ['POST'])]
    public function bulk(Request $request, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        // Validation des champs partagés (hors plante).
        $errors = $this->validateShared($data);

        $ids = $data['vegetables'] ?? [];
        $vegetables = [];
        if (\is_array($ids)) {
            foreach ($ids as $id) {
                $v = $this->vegetables->find((int) $id);
                if (null !== $v && $v->getUtilisateur() === $user) {
                    $vegetables[] = $v;
                }
            }
        }
        if ([] === $vegetables) {
            $errors['vegetables'] = 'Aucune plante valide sélectionnée.';
        }

        if ([] !== $errors) {
            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        foreach ($vegetables as $vegetable) {
            $action = new Action();
            $action->setUtilisateur($user);
            $action->setVegetable($vegetable);
            $this->applyShared($action, $data);
            $this->em->persist($action);
        }
        $this->em->flush();

        return new JsonResponse(['created' => \count($vegetables)], Response::HTTP_CREATED);
    }

    #[Route('/actions/{id}', name: 'api_action_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Request $request, Action $action, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $action);

        $errors = $this->apply($action, json_decode($request->getContent(), true) ?? [], $user);
        if ([] !== $errors) {
            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->flush();

        return new JsonResponse($this->serialize($action));
    }

    #[Route('/actions/{id}', name: 'api_action_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Action $action): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::DELETE, $action);

        $this->em->remove($action);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Upload de photos rattachées à l'intervention ET à sa plante (comme le
     * legacy : l'input file du formulaire d'action).
     */
    #[Route('/actions/{id}/photos', name: 'api_action_photos_upload', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function uploadPhotos(Request $request, Action $action, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $action);

        $files = $request->files->all()['images'] ?? [];
        $files = \is_array($files) ? $files : [$files];
        $files = array_filter($files, static fn ($f) => $f instanceof \Symfony\Component\HttpFoundation\File\UploadedFile);

        if ([] === $files) {
            return new JsonResponse(['message' => 'Aucun fichier reçu.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        foreach ($files as $file) {
            if (!str_starts_with((string) $file->getMimeType(), 'image/')) {
                return new JsonResponse(['message' => 'Seules les images sont acceptées.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $photo = new \App\Entity\Photo();
            $photo->setUtilisateur($user);
            $photo->setVegetable($action->getVegetable());
            $photo->setAction($action);
            $photo->setImageFile($file);
            $this->em->persist($photo);
        }
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_CREATED);
    }

    /**
     * Validation + remplissage pour une action liée à une seule plante.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    private function apply(Action $action, array $data, Utilisateur $user): array
    {
        $errors = $this->validateShared($data);

        // Plante (obligatoire, possédée)
        $vegetableId = $data['vegetable'] ?? null;
        $vegetable = (null !== $vegetableId && '' !== $vegetableId) ? $this->vegetables->find((int) $vegetableId) : null;
        if (null === $vegetable || $vegetable->getUtilisateur() !== $user) {
            $errors['vegetable'] = 'Plante invalide ou obligatoire.';
        } else {
            $action->setVegetable($vegetable);
        }

        if ([] === $errors) {
            $this->applyShared($action, $data);
        }

        return $errors;
    }

    /**
     * Valide les champs partagés (titre, type), hors plante.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    private function validateShared(array $data): array
    {
        $errors = [];
        if ('' === trim((string) ($data['title'] ?? ''))) {
            $errors['title'] = 'Le titre est obligatoire.';
        }
        if (!\in_array((string) ($data['typeAction'] ?? ''), Action::TYPES_ACTION, true)) {
            $errors['typeAction'] = 'Type d\'intervention invalide.';
        }

        return $errors;
    }

    /**
     * Pose les champs partagés sur l'action (titre, type, commentaire, date).
     *
     * @param array<string, mixed> $data
     */
    private function applyShared(Action $action, array $data): void
    {
        $action->setTitle(trim((string) $data['title']));
        $action->setTypeAction((string) $data['typeAction']);

        $comment = $data['comment'] ?? null;
        $action->setComment(is_string($comment) && '' !== trim($comment) ? $comment : null);

        // Date : fournie sinon maintenant.
        $date = $data['date'] ?? null;
        try {
            $action->setDate(is_string($date) && '' !== $date ? new \DateTime($date) : new \DateTime());
        } catch (\Exception) {
            $action->setDate(new \DateTime());
        }
    }
}
