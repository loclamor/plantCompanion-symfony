<?php

namespace App\Controller\Api;

use App\Entity\Action;
use App\Entity\Photo;
use App\Entity\Utilisateur;
use App\Entity\Vegetable;
use App\Repository\PhotoRepository;
use App\Repository\VegetableRepository;
use App\Security\Voter\OwnerVoter;
use App\Service\PhotoPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Gestion des photos d'une plante depuis le SPA : upload (multipart), photo par
 * défaut, suppression. Réutilise Vich (setImageFile), OwnerVoter et la purge
 * Liip. Chaque mutation renvoie la liste des photos à jour de la plante.
 */
#[Route('/api')]
final class PhotoApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PhotoRepository $photos,
        private readonly PhotoPresenter $presenter,
        private readonly VegetableRepository $vegetables,
    ) {
    }

    private function listPayload(Vegetable $vegetable): array
    {
        return ['photos' => $this->presenter->presentMany(
            $this->photos->findByVegetable($vegetable),
            $vegetable->getDefaultPhoto(),
        )];
    }

    #[Route('/vegetables/{id}/photos', name: 'api_vegetable_photos_upload', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function upload(Request $request, Vegetable $vegetable, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $vegetable);

        // Champ "images" : un ou plusieurs fichiers.
        $files = $request->files->all()['images'] ?? $request->files->get('image');
        $files = \is_array($files) ? $files : (null !== $files ? [$files] : []);
        $files = array_filter($files, static fn ($f) => $f instanceof UploadedFile);

        if ([] === $files) {
            return new JsonResponse(['message' => 'Aucun fichier reçu.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        foreach ($files as $file) {
            /** @var UploadedFile $file */
            if (!str_starts_with((string) $file->getMimeType(), 'image/')) {
                return new JsonResponse(['message' => 'Seules les images sont acceptées.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $photo = new Photo();
            $photo->setUtilisateur($user);
            $photo->setVegetable($vegetable);
            $photo->setImageFile($file);
            $this->em->persist($photo);
        }
        $this->em->flush();

        return new JsonResponse($this->listPayload($vegetable), Response::HTTP_CREATED);
    }

    #[Route('/photos/{id}/default', name: 'api_photo_set_default', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function setDefault(Photo $photo): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::VIEW, $photo);
        $vegetable = $photo->getVegetable();
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $vegetable);

        $vegetable->setDefaultPhoto($photo);
        $this->em->flush();

        return new JsonResponse($this->listPayload($vegetable));
    }

    #[Route('/photos/{id}', name: 'api_photo_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Photo $photo, CacheManager $cacheManager): JsonResponse
    {
        $this->denyAccessUnlessGranted(OwnerVoter::DELETE, $photo);
        $vegetable = $photo->getVegetable();

        // Dénoue la photo par défaut si nécessaire.
        if (null !== $vegetable && $vegetable->getDefaultPhoto() === $photo) {
            $vegetable->setDefaultPhoto(null);
        }

        // Purge des miniatures Liip ; Vich supprime l'original (delete_on_remove).
        $relative = $photo->getRelativePath();
        if (null !== $relative) {
            $cacheManager->remove($relative);
        }

        $this->em->remove($photo);
        $this->em->flush();

        return null !== $vegetable
            ? new JsonResponse($this->listPayload($vegetable))
            : new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Import par lot (principe de l'écran uploadMultipleV2 legacy) : une photo +
     * son affectation. Rattache la photo à une plante ; si un type d'action est
     * fourni (≠ « Sans action »), crée aussi l'intervention liée. Une requête
     * par photo (le front boucle), pas d'orphelins.
     */
    #[Route('/photos/import', name: 'api_photo_import', methods: ['POST'])]
    public function import(Request $request, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $file = $request->files->get('image');
        if (!$file instanceof UploadedFile || !str_starts_with((string) $file->getMimeType(), 'image/')) {
            return new JsonResponse(['message' => 'Image manquante ou invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $vegetableId = $request->request->get('vegetable');
        $vegetable = ('' !== (string) $vegetableId) ? $this->vegetables->find((int) $vegetableId) : null;
        if (null === $vegetable || $vegetable->getUtilisateur() !== $user) {
            return new JsonResponse(['errors' => ['vegetable' => 'Plante invalide ou obligatoire.']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $photo = new Photo();
        $photo->setUtilisateur($user);
        $photo->setVegetable($vegetable);
        $photo->setImageFile($file);

        // Intervention optionnelle.
        $typeAction = (string) $request->request->get('typeAction', '');
        $actionId = null;
        if ('' !== $typeAction && 'none' !== $typeAction) {
            if (!\in_array($typeAction, Action::TYPES_ACTION, true)) {
                return new JsonResponse(['errors' => ['typeAction' => 'Type d\'intervention invalide.']], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $title = trim((string) $request->request->get('title', ''));
            if ('' === $title) {
                return new JsonResponse(['errors' => ['title' => 'Le titre est obligatoire.']], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $action = new Action();
            $action->setUtilisateur($user);
            $action->setVegetable($vegetable);
            $action->setTypeAction($typeAction);
            $action->setTitle($title);
            $comment = (string) $request->request->get('comment', '');
            $action->setComment('' !== trim($comment) ? $comment : null);
            $dateRaw = (string) $request->request->get('date', '');
            try {
                $action->setDate('' !== $dateRaw ? new \DateTime($dateRaw) : new \DateTime());
            } catch (\Exception) {
                $action->setDate(new \DateTime());
            }
            $this->em->persist($action);
            $photo->setAction($action);

            $this->em->persist($photo);
            $this->em->flush();
            $actionId = $action->getId();

            return new JsonResponse(['photoId' => $photo->getId(), 'actionId' => $actionId], Response::HTTP_CREATED);
        }

        $this->em->persist($photo);
        $this->em->flush();

        return new JsonResponse(['photoId' => $photo->getId(), 'actionId' => null], Response::HTTP_CREATED);
    }
}
