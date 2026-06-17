<?php

namespace App\Entity;

/**
 * Implémentée par toutes les entités possédées par un Utilisateur (multi-utilisateur).
 * Permet un scoping et un contrôle d'accès génériques (cf. OwnerVoter).
 */
interface UserOwnedInterface
{
    public function getUtilisateur(): ?Utilisateur;
}
