<?php

namespace App\Service;

use App\Entity\BacSaison;
use App\Entity\Saison;
use App\Entity\Utilisateur;
use App\Repository\BacRepository;
use App\Repository\BacSaisonRepository;
use App\Repository\SaisonRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Cycle de vie des saisons du potager : démarrage d'une nouvelle saison avec
 * report. Clôture la saison active courante, persiste la nouvelle saison, et
 * recopie la géométrie des bacs non archivés dans de nouveaux BacSaison.
 *
 * La grainothèque est hors saison (inchangée) ; les semis et cultures annuelles
 * ne sont pas reportés. Le report des cultures **pérennes** arrivera en Phase 5
 * (l'entité Culture n'existe pas encore).
 */
final class SaisonCycleService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SaisonRepository $saisons,
        private readonly BacRepository $bacs,
        private readonly BacSaisonRepository $bacSaisons,
    ) {
    }

    /**
     * Démarre $newSaison comme nouvelle saison active pour $user (la saison déjà
     * validée et renseignée par l'appelant), en clôturant l'éventuelle active et
     * en recopiant la géométrie des bacs. $newSaison ne doit pas encore être
     * persistée.
     */
    public function startNewSeason(Utilisateur $user, Saison $newSaison): Saison
    {
        // 1. Clôturer la saison active courante (une seule active par utilisateur).
        $active = $this->saisons->findActiveForUser($user);
        if (null !== $active && $active !== $newSaison) {
            $active->setStatut(Saison::STATUT_CLOTUREE);
        }

        // 2. Persister la nouvelle saison active.
        $newSaison->setUtilisateur($user)->setStatut(Saison::STATUT_ACTIVE);
        $this->em->persist($newSaison);

        // 3. Recopier la géométrie de chaque bac non archivé dans un BacSaison.
        foreach ($this->bacs->findActiveByUser($user) as $bac) {
            $this->createSnapshot($user, $bac, $newSaison);
        }

        // 4. Phase 5 : recopier les Culture pérennes « en_place » + lier parentCulture.
        // 5. Grainothèque inchangée ; semis/cultures annuelles non reportés (rien à faire).

        $this->em->flush();

        return $newSaison;
    }

    /**
     * Crée (sans flush) un BacSaison pour $bac dans $saison, en reprenant la
     * géométrie de son dernier snapshot connu, sinon les valeurs par défaut du
     * bac. Utilisé au démarrage d'une saison et à la création d'un bac (ajout au
     * snapshot de la saison active courante).
     */
    public function createSnapshot(Utilisateur $user, \App\Entity\Bac $bac, Saison $saison): BacSaison
    {
        $last = $this->bacSaisons->findLastForBac($bac);

        $bs = (new BacSaison())
            ->setUtilisateur($user)
            ->setBac($bac)
            ->setSaison($saison);

        if (null !== $last) {
            // Reprise du dernier snapshot connu (taille physique + position + découpage).
            $bs->setLargeur($last->getLargeur())
                ->setLongueur($last->getLongueur())
                ->setPosX($last->getPosX())
                ->setPosY($last->getPosY())
                ->setLignes($last->getLignes())
                ->setColonnes($last->getColonnes());
        } else {
            // Premier report du bac : valeurs par défaut, position en haut à gauche.
            $bs->setLargeur($bac->getLargeurDefaut())
                ->setLongueur($bac->getLongueurDefaut())
                ->setPosX(0)
                ->setPosY(0)
                ->setLignes($bac->getLignesDefaut())
                ->setColonnes($bac->getColonnesDefaut());
        }

        $this->em->persist($bs);

        return $bs;
    }
}
