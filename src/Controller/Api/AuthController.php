<?php

namespace App\Controller\Api;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Service\CurrentGroup;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Authentification du SPA par session/cookie (pas de JWT). Le SPA est servi
 * same-origin, le cookie de session suffit. Réutilise le firewall `main`.
 */
#[Route('/api')]
final class AuthController extends AbstractController
{
    /**
     * Sérialisation publique de l'utilisateur : id + nom + rôles uniquement.
     * Aucune PII (pas d'email, pas de hash).
     */
    private function userPayload(Utilisateur $user): array
    {
        return [
            'id' => $user->getId(),
            'name' => $user->getName(),
            'roles' => $user->getRoles(),
        ];
    }

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(
        Request $request,
        UtilisateurRepository $users,
        UserPasswordHasherInterface $hasher,
        Security $security,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];
        $name = (string) ($data['name'] ?? '');
        $password = (string) ($data['password'] ?? '');

        $user = '' !== $name ? $users->findOneBy(['name' => $name]) : null;

        if (null === $user || !$hasher->isPasswordValid($user, $password)) {
            return new JsonResponse(['message' => 'Identifiants invalides.'], Response::HTTP_UNAUTHORIZED);
        }

        $security->login($user, 'form_login', 'main');

        return new JsonResponse($this->userPayload($user));
    }

    #[Route('/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(Security $security): JsonResponse
    {
        $security->logout(false);

        return new JsonResponse(['message' => 'Déconnecté.']);
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?Utilisateur $user): JsonResponse
    {
        if (null === $user) {
            return new JsonResponse(['message' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse($this->userPayload($user));
    }

    #[Route('/current-group', name: 'api_current_group_get', methods: ['GET'])]
    public function getCurrentGroup(#[CurrentUser] Utilisateur $user, CurrentGroup $currentGroup): JsonResponse
    {
        $group = $currentGroup->resolve($user);

        return new JsonResponse(['id' => $group?->getId()]);
    }

    #[Route('/current-group', name: 'api_current_group_set', methods: ['PUT'])]
    public function setCurrentGroup(Request $request, CurrentGroup $currentGroup): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $id = $data['id'] ?? null;
        $currentGroup->set(null === $id ? null : (int) $id);

        return new JsonResponse(['id' => null === $id ? null : (int) $id]);
    }
}
