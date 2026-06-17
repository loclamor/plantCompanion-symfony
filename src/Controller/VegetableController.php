<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Entity\Vegetable;
use App\Form\VegetableType;
use App\Repository\ActionRepository;
use App\Repository\PhotoRepository;
use App\Repository\TypeRepository;
use App\Repository\VegetableHistoryRepository;
use App\Repository\VegetableRepository;
use App\Security\Voter\OwnerVoter;
use App\Service\CurrentGroup;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/vegetable')]
final class VegetableController extends AbstractController
{
    private const PAGE_SIZE = 12;

    #[Route(name: 'app_vegetable_index', methods: ['GET'])]
    public function index(Request $request, VegetableRepository $vegetableRepository, TypeRepository $typeRepository, CurrentGroup $currentGroup): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();
        $session = $request->getSession();

        $defaults = ['q' => '', 'type' => null, 'sort' => 'name', 'dir' => 'asc'];
        /** @var array{q: string, type: ?int, sort: string, dir: string} $filters */
        $filters = $session->get('vegetable_filters', $defaults);

        if ($request->query->getBoolean('reset')) {
            $filters = $defaults;
        } else {
            if ($request->query->has('q')) {
                $filters['q'] = trim($request->query->getString('q'));
            }
            if ($request->query->has('type')) {
                $filters['type'] = $request->query->getInt('type') ?: null;
            }
            if ($request->query->has('sort')) {
                $filters['sort'] = $request->query->getString('sort');
            }
            if ($request->query->has('dir')) {
                $filters['dir'] = $request->query->getString('dir');
            }
        }
        $session->set('vegetable_filters', $filters);

        $group = $currentGroup->resolve($user);

        $type = null;
        if (null !== $filters['type']) {
            $type = $typeRepository->find($filters['type']);
            if (null === $type || $type->getUtilisateur() !== $user) {
                $type = null;
            }
        }

        $page = max(1, $request->query->getInt('page', 1));
        $total = $vegetableRepository->countByUserFiltered($user, $group, $filters['q'], $type);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($page, $pages);

        $vegetables = $vegetableRepository->findByUserFiltered(
            $user,
            $group,
            $filters['q'],
            $type,
            $filters['sort'],
            $filters['dir'],
            self::PAGE_SIZE,
            ($page - 1) * self::PAGE_SIZE,
        );

        return $this->render('vegetable/index.html.twig', [
            'vegetables' => $vegetables,
            'types' => $typeRepository->findByUser($user),
            'filters' => $filters,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'current_group' => $group,
        ]);
    }

    #[Route('/new', name: 'app_vegetable_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $vegetable = new Vegetable();
        $vegetable->setUtilisateur($user);
        $vegetable->setCreationDate(new \DateTime());
        $vegetable->setAddDate(new \DateTime());

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
    public function show(Vegetable $vegetable, ActionRepository $actionRepository, VegetableHistoryRepository $historyRepository, PhotoRepository $photoRepository): Response
    {
        $this->denyAccessUnlessGranted(OwnerVoter::VIEW, $vegetable);

        return $this->render('vegetable/show.html.twig', [
            'vegetable' => $vegetable,
            'photos' => $photoRepository->findByVegetable($vegetable),
            'actions' => $actionRepository->findByVegetable($vegetable),
            'histories' => $historyRepository->findByVegetable($vegetable),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_vegetable_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Vegetable $vegetable, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(OwnerVoter::EDIT, $vegetable);

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
        $this->denyAccessUnlessGranted(OwnerVoter::DELETE, $vegetable);

        if ($this->isCsrfTokenValid('delete'.$vegetable->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($vegetable);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_vegetable_index', [], Response::HTTP_SEE_OTHER);
    }
}
