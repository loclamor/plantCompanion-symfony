<?php

namespace App\Controller;

use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Form\TypeType;
use App\Repository\TypeRepository;
use App\Security\Voter\OwnerVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/type')]
final class TypeController extends AbstractController
{
    #[Route(name: 'app_type_index', methods: ['GET'])]
    public function index(TypeRepository $typeRepository): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        return $this->render('type/index.html.twig', [
            'types' => $typeRepository->findByUser($user),
        ]);
    }

    #[Route('/new', name: 'app_type_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $type = new Type();
        $type->setUtilisateur($user);

        $form = $this->createForm(TypeType::class, $type);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($type);
            $entityManager->flush();

            return $this->redirectToRoute('app_type_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('type/new.html.twig', [
            'type' => $type,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_type_show', methods: ['GET'])]
    public function show(Type $type): Response
    {
        $this->denyAccessUnlessGranted(OwnerVoter::VIEW, $type);

        return $this->render('type/show.html.twig', [
            'type' => $type,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_type_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Type $type, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $type);

        $form = $this->createForm(TypeType::class, $type);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_type_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('type/edit.html.twig', [
            'type' => $type,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_type_delete', methods: ['POST'])]
    public function delete(Request $request, Type $type, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(OwnerVoter::DELETE, $type);

        if ($this->isCsrfTokenValid('delete'.$type->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($type);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_type_index', [], Response::HTTP_SEE_OTHER);
    }
}
