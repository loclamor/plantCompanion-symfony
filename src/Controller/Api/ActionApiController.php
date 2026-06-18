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

        return [
            'id' => $a->getId(),
            'date' => $a->getDate()?->format(\DateTimeInterface::ATOM),
            'typeAction' => $a->getTypeAction(),
            'title' => $a->getTitle(),
            'comment' => $a->getComment(),
            'vegetable' => $v ? ['id' => $v->getId(), 'name' => $v->getName()] : null,
        ];
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
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    private function apply(Action $action, array $data, Utilisateur $user): array
    {
        $errors = [];

        // Plante (obligatoire, possédée)
        $vegetableId = $data['vegetable'] ?? null;
        $vegetable = (null !== $vegetableId && '' !== $vegetableId) ? $this->vegetables->find((int) $vegetableId) : null;
        if (null === $vegetable || $vegetable->getUtilisateur() !== $user) {
            $errors['vegetable'] = 'Plante invalide ou obligatoire.';
        } else {
            $action->setVegetable($vegetable);
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ('' === $title) {
            $errors['title'] = 'Le titre est obligatoire.';
        } else {
            $action->setTitle($title);
        }

        $type = (string) ($data['typeAction'] ?? '');
        if (!\in_array($type, Action::TYPES_ACTION, true)) {
            $errors['typeAction'] = 'Type d\'intervention invalide.';
        } else {
            $action->setTypeAction($type);
        }

        $comment = $data['comment'] ?? null;
        $action->setComment(is_string($comment) && '' !== trim($comment) ? $comment : null);

        // Date : fournie sinon maintenant.
        $date = $data['date'] ?? null;
        try {
            $action->setDate(is_string($date) && '' !== $date ? new \DateTime($date) : new \DateTime());
        } catch (\Exception) {
            $action->setDate(new \DateTime());
        }

        return $errors;
    }
}
