<?php

namespace App\Controller;

use App\Entity\PorteGreffe;
use App\Entity\Utilisateur;
use App\Form\PorteGreffeType;
use App\Repository\PorteGreffeRepository;
use App\Security\Voter\OwnerVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/porte-greffe')]
final class PorteGreffeController extends AbstractController
{
    #[Route(name: 'app_porte_greffe_index', methods: ['GET'])]
    public function index(PorteGreffeRepository $porteGreffeRepository): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        return $this->render('porte_greffe/index.html.twig', [
            'porte_greffes' => $porteGreffeRepository->findByUser($user),
        ]);
    }

    #[Route('/new', name: 'app_porte_greffe_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $porteGreffe = new PorteGreffe();
        $porteGreffe->setUtilisateur($user);

        $form = $this->createForm(PorteGreffeType::class, $porteGreffe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($porteGreffe);
            $entityManager->flush();

            return $this->redirectToRoute('app_porte_greffe_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('porte_greffe/new.html.twig', [
            'porte_greffe' => $porteGreffe,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_porte_greffe_show', methods: ['GET'])]
    public function show(PorteGreffe $porteGreffe): Response
    {
        $this->denyAccessUnlessGranted(OwnerVoter::VIEW, $porteGreffe);

        return $this->render('porte_greffe/show.html.twig', [
            'porte_greffe' => $porteGreffe,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_porte_greffe_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PorteGreffe $porteGreffe, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $porteGreffe);

        $form = $this->createForm(PorteGreffeType::class, $porteGreffe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_porte_greffe_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('porte_greffe/edit.html.twig', [
            'porte_greffe' => $porteGreffe,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_porte_greffe_delete', methods: ['POST'])]
    public function delete(Request $request, PorteGreffe $porteGreffe, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(OwnerVoter::DELETE, $porteGreffe);

        if ($this->isCsrfTokenValid('delete'.$porteGreffe->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($porteGreffe);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_porte_greffe_index', [], Response::HTTP_SEE_OTHER);
    }
}
