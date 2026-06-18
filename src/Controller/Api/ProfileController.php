<?php

namespace App\Controller\Api;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Profil de l'utilisateur connecté (modification de son propre mot de passe).
 */
#[Route('/api/me')]
final class ProfileController extends AbstractController
{
    #[Route('/password', name: 'api_me_password', methods: ['PUT'])]
    public function changePassword(
        Request $request,
        #[CurrentUser] Utilisateur $user,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];
        $current = (string) ($data['currentPassword'] ?? '');
        $new = (string) ($data['newPassword'] ?? '');

        $errors = [];
        if (!$hasher->isPasswordValid($user, $current)) {
            $errors['currentPassword'] = 'Mot de passe actuel incorrect.';
        }
        if (mb_strlen($new) < 6) {
            $errors['newPassword'] = 'Le nouveau mot de passe doit faire au moins 6 caractères.';
        }
        if ([] !== $errors) {
            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->setPassword($hasher->hashPassword($user, $new));
        $em->flush();

        return new JsonResponse(['message' => 'Mot de passe mis à jour.']);
    }
}
