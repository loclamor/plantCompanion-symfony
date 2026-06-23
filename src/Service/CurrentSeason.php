<?php

namespace App\Service;

use App\Entity\Saison;
use App\Entity\Utilisateur;
use App\Repository\SaisonRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Gère la « saison courante » mémorisée en session (sélecteur de saison de la
 * navbar du module Potager). Calqué sur CurrentGroup. Si rien n'est en session,
 * retombe sur la saison active de l'utilisateur.
 */
final class CurrentSeason
{
    private const SESSION_KEY = 'current_season';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly SaisonRepository $saisonRepository,
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
     * La saison courante si elle appartient à l'utilisateur ; à défaut, la
     * saison active de l'utilisateur ; sinon null.
     */
    public function resolve(Utilisateur $user): ?Saison
    {
        $id = $this->getId();
        if (null !== $id) {
            $saison = $this->saisonRepository->find($id);
            if (null !== $saison && $saison->getUtilisateur() === $user) {
                return $saison;
            }
        }

        return $this->saisonRepository->findActiveForUser($user);
    }
}
