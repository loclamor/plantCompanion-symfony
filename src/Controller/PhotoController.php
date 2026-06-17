<?php

namespace App\Controller;

use App\Entity\Photo;
use App\Entity\Utilisateur;
use App\Entity\Vegetable;
use App\Form\PhotoType;
use App\Repository\VegetableRepository;
use App\Security\Voter\OwnerVoter;
use App\Service\ExifDateExtractor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Image;

#[Route('/photo')]
final class PhotoController extends AbstractController
{
    #[Route('/new', name: 'app_photo_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ExifDateExtractor $exifExtractor, VegetableRepository $vegetableRepository): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $photo = new Photo();
        $photo->setUtilisateur($user);

        // Pré-sélection éventuelle de la plante via ?vegetable=ID (lien depuis la fiche).
        $vegetableId = $request->query->getInt('vegetable');
        if ($vegetableId > 0) {
            $vegetable = $vegetableRepository->find($vegetableId);
            if (null !== $vegetable && $vegetable->getUtilisateur() === $user) {
                $photo->setVegetable($vegetable);
            }
        }

        $form = $this->createForm(PhotoType::class, $photo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // EXIF (confort) : signaler la date de prise de vue avant que Vich ne déplace le fichier.
            $uploaded = $photo->getImageFile();
            if ($uploaded instanceof UploadedFile) {
                $exifDate = $exifExtractor->extractDate($uploaded->getPathname());
                if (null !== $exifDate) {
                    $this->addFlash('success', 'Date EXIF détectée : '.$exifDate->format('Y-m-d'));
                }
            }

            $entityManager->persist($photo);
            $entityManager->flush();

            return $this->redirectToRoute('app_vegetable_show', ['id' => $photo->getVegetable()->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('photo/new.html.twig', [
            'photo' => $photo,
            'form' => $form,
        ]);
    }

    #[Route('/upload-multiple', name: 'app_photo_upload_multiple', methods: ['GET', 'POST'])]
    public function uploadMultiple(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $form = $this->createFormBuilder()
            ->add('vegetable', EntityType::class, [
                'class' => Vegetable::class,
                'choice_label' => 'name',
                'query_builder' => static fn (EntityRepository $repo) => $repo->createQueryBuilder('e')
                    ->andWhere('e.utilisateur = :user')
                    ->setParameter('user', $user)
                    ->orderBy('e.name', 'ASC'),
            ])
            ->add('images', FileType::class, [
                'label' => 'Photos',
                'multiple' => true,
                'mapped' => false,
                'constraints' => [new All(['constraints' => [new Image()]])],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Vegetable $vegetable */
            $vegetable = $form->get('vegetable')->getData();
            /** @var UploadedFile[] $files */
            $files = $form->get('images')->getData();

            foreach ($files as $file) {
                $photo = new Photo();
                $photo->setUtilisateur($user);
                $photo->setVegetable($vegetable);
                $photo->setImageFile($file);
                $entityManager->persist($photo);
            }
            $entityManager->flush();

            $this->addFlash('success', \count($files).' photo(s) ajoutée(s).');

            return $this->redirectToRoute('app_vegetable_show', ['id' => $vegetable->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('photo/upload_multiple.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/default', name: 'app_photo_set_default', methods: ['POST'])]
    public function setDefault(Request $request, Photo $photo, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(OwnerVoter::VIEW, $photo);
        $vegetable = $photo->getVegetable();
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $vegetable);

        if ($this->isCsrfTokenValid('default'.$photo->getId(), $request->getPayload()->getString('_token'))) {
            $vegetable->setDefaultPhoto($photo);
            $entityManager->flush();
            $this->addFlash('success', 'Photo par défaut définie.');
        }

        return $this->redirectToRoute('app_vegetable_show', ['id' => $vegetable->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'app_photo_delete', methods: ['POST'])]
    public function delete(Request $request, Photo $photo, EntityManagerInterface $entityManager, CacheManager $cacheManager): Response
    {
        $this->denyAccessUnlessGranted(OwnerVoter::DELETE, $photo);
        $vegetableId = $photo->getVegetable()?->getId();

        if ($this->isCsrfTokenValid('delete'.$photo->getId(), $request->getPayload()->getString('_token'))) {
            // Si cette photo était la photo par défaut, on dénoue la référence.
            $vegetable = $photo->getVegetable();
            if (null !== $vegetable && $vegetable->getDefaultPhoto() === $photo) {
                $vegetable->setDefaultPhoto(null);
            }

            // Purge des miniatures Liip ; Vich supprime le fichier original (delete_on_remove).
            $relative = $photo->getRelativePath();
            if (null !== $relative) {
                $cacheManager->remove($relative);
            }

            $entityManager->remove($photo);
            $entityManager->flush();
        }

        if (null !== $vegetableId) {
            return $this->redirectToRoute('app_vegetable_show', ['id' => $vegetableId], Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('app_vegetable_index', [], Response::HTTP_SEE_OTHER);
    }
}
