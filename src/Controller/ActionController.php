<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Utilisateur;
use App\Form\ActionType;
use App\Repository\ActionRepository;
use App\Security\Voter\OwnerVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/action')]
final class ActionController extends AbstractController
{
    #[Route(name: 'app_action_index', methods: ['GET'])]
    public function index(ActionRepository $actionRepository): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        return $this->render('action/index.html.twig', [
            'actions' => $actionRepository->findByUser($user),
        ]);
    }

    #[Route('/new', name: 'app_action_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $action = new Action();
        $action->setUtilisateur($user);
        $action->setDate(new \DateTime());

        $form = $this->createForm(ActionType::class, $action);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($action);
            $entityManager->flush();

            return $this->redirectToRoute('app_action_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('action/new.html.twig', [
            'action' => $action,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_action_show', methods: ['GET'])]
    public function show(Action $action): Response
    {
        $this->denyAccessUnlessGranted(OwnerVoter::VIEW, $action);

        return $this->render('action/show.html.twig', [
            'action' => $action,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_action_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Action $action, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $action);

        $form = $this->createForm(ActionType::class, $action);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_action_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('action/edit.html.twig', [
            'action' => $action,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_action_delete', methods: ['POST'])]
    public function delete(Request $request, Action $action, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(OwnerVoter::DELETE, $action);

        if ($this->isCsrfTokenValid('delete'.$action->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($action);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_action_index', [], Response::HTTP_SEE_OTHER);
    }
}
