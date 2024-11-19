<?php

namespace App\Controller;

use App\Entity\Vegetable;
use App\Form\VegetableType;
use App\Repository\VegetableRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/vegetable')]
final class VegetableController extends AbstractController
{
    #[Route(name: 'app_vegetable_index', methods: ['GET'])]
    public function index(VegetableRepository $vegetableRepository): Response
    {
        return $this->render('vegetable/index.html.twig', [
            'vegetables' => $vegetableRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_vegetable_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $vegetable = new Vegetable();
        $form = $this->createForm(VegetableType::class, $vegetable);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($vegetable);
            $entityManager->flush();

            return $this->redirectToRoute('app_vegetable_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('vegetable/new.html.twig', [
            'vegetable' => $vegetable,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_vegetable_show', methods: ['GET'])]
    public function show(Vegetable $vegetable): Response
    {
        return $this->render('vegetable/show.html.twig', [
            'vegetable' => $vegetable,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_vegetable_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Vegetable $vegetable, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(VegetableType::class, $vegetable);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_vegetable_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('vegetable/edit.html.twig', [
            'vegetable' => $vegetable,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_vegetable_delete', methods: ['POST'])]
    public function delete(Request $request, Vegetable $vegetable, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$vegetable->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($vegetable);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_vegetable_index', [], Response::HTTP_SEE_OTHER);
    }
}
