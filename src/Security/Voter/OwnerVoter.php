<?php

namespace App\Security\Voter;

use App\Entity\UserOwnedInterface;
use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Contrôle d'accès générique aux entités possédées par un utilisateur.
 * Une entité n'est accessible (lecture/édition/suppression) que par son propriétaire.
 */
final class OwnerVoter extends Voter
{
    public const VIEW = 'OWNER_VIEW';
    public const EDIT = 'OWNER_EDIT';
    public const DELETE = 'OWNER_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)
            && $subject instanceof UserOwnedInterface;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof Utilisateur) {
            return false;
        }

        /** @var UserOwnedInterface $subject */
        return $subject->getUtilisateur() === $user;
    }
}
