<?php

namespace App\Service;

use App\Entity\Group;
use App\Entity\Utilisateur;
use App\Repository\GroupRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Gère le « groupe courant » mémorisé en session (équivalent du sélecteur de
 * groupe de la navbar du legacy). Filtre les plantes/actions affichées.
 */
final class CurrentGroup
{
    private const SESSION_KEY = 'current_group';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly GroupRepository $groupRepository,
    ) {
    }

    public function getId(): ?int
    {
        $id = $this->requestStack->getSession()->get(self::SESSION_KEY);

        return \is_int($id) ? $id : null;
    }

    public function set(?int $id): void
    {
        $session = $this->requestStack->getSession();
        if (null === $id) {
            $session->remove(self::SESSION_KEY);
        } else {
            $session->set(self::SESSION_KEY, $id);
        }
    }

    /**
     * Le groupe courant s'il appartient bien à l'utilisateur, sinon null.
     */
    public function resolve(Utilisateur $user): ?Group
    {
        $id = $this->getId();
        if (null === $id) {
            return null;
        }

        $group = $this->groupRepository->find($id);

        return (null !== $group && $group->getUtilisateur() === $user) ? $group : null;
    }
}
