<?php

namespace App\Controller\Api;

use App\Entity\UserOwnedInterface;
use App\Entity\Utilisateur;
use App\Security\Voter\OwnerVoter;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base des CRUD JSON des entités de paramétrage possédées par l'utilisateur
 * (Type, Group, Lieu, PorteGreffe). Réutilise OwnerVoter pour l'accès. Les
 * sous-classes déclarent leurs routes (préfixe propre) et délèguent à ces
 * méthodes do*().
 */
abstract class AbstractOwnedCrudApiController extends AbstractController
{
    public function __construct(protected readonly EntityManagerInterface $em)
    {
    }

    /** Repository de l'entité (expose find() et findByUser()). */
    abstract protected function getRepository(): object;

    abstract protected function createEntity(Utilisateur $user): UserOwnedInterface;

    /**
     * Remplit l'entité depuis le payload. Retourne les erreurs (vide si OK).
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    abstract protected function applyPayload(UserOwnedInterface $entity, array $data, Utilisateur $user): array;

    /** @return array<string, mixed> */
    abstract protected function serialize(UserOwnedInterface $entity): array;

    protected function doList(Utilisateur $user): JsonResponse
    {
        return new JsonResponse([
            'items' => array_map(fn ($e) => $this->serialize($e), $this->getRepository()->findByUser($user)),
        ]);
    }

    protected function doShow(UserOwnedInterface $entity): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::VIEW, $entity);

        return new JsonResponse($this->serialize($entity));
    }

    protected function doCreate(Request $request, Utilisateur $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $entity = $this->createEntity($user);

        $errors = $this->applyPayload($entity, $data, $user);
        if ([] !== $errors) {
            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($entity);
        $this->em->flush();

        return new JsonResponse($this->serialize($entity), Response::HTTP_CREATED);
    }

    protected function doUpdate(Request $request, UserOwnedInterface $entity, Utilisateur $user): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $entity);

        $data = json_decode($request->getContent(), true) ?? [];
        $errors = $this->applyPayload($entity, $data, $user);
        if ([] !== $errors) {
            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->flush();

        return new JsonResponse($this->serialize($entity));
    }

    protected function doDelete(UserOwnedInterface $entity): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::DELETE, $entity);

        try {
            $this->em->remove($entity);
            $this->em->flush();
        } catch (ForeignKeyConstraintViolationException) {
            return new JsonResponse(
                ['message' => 'Suppression impossible : cet élément est encore utilisé.'],
                Response::HTTP_CONFLICT,
            );
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Entité référencée par id si elle appartient à l'utilisateur, sinon null.
     */
    protected function resolveOwned(object $repository, mixed $id, Utilisateur $user): ?object
    {
        if (null === $id || '' === $id) {
            return null;
        }
        $entity = $repository->find((int) $id);

        return ($entity instanceof UserOwnedInterface && $entity->getUtilisateur() === $user) ? $entity : null;
    }

    /** Helper commun : valide et pose le nom, retourne l'erreur éventuelle. */
    protected function applyName(UserOwnedInterface $entity, array $data): ?string
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ('' === $name) {
            return 'Le nom est obligatoire.';
        }
        $entity->setName($name);

        return null;
    }
}
