<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\TypeRepository;
use App\Repository\VegetableRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/print')]
final class PrintController extends AbstractController
{
    #[Route('/bytype', name: 'app_print_bytype', methods: ['GET'])]
    public function byType(Request $request, VegetableRepository $vegetableRepository, TypeRepository $typeRepository): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $type = null;
        $typeRaw = $request->query->get('type');
        $typeId = ('' !== $typeRaw && null !== $typeRaw) ? (int) $typeRaw : 0;
        if ($typeId > 0) {
            $type = $typeRepository->find($typeId);
            if (null === $type || $type->getUtilisateur() !== $user) {
                $type = null;
            }
        }

        $vegetables = null !== $type
            ? $vegetableRepository->findByUserFiltered($user, null, null, $type, 'name', 'asc', 1000, 0)
            : [];

        return $this->render('print/bytype.html.twig', [
            'types' => $typeRepository->findByUser($user),
            'selectedType' => $type,
            'vegetables' => $vegetables,
        ]);
    }
}
