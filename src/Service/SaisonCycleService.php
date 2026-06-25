<?php

namespace App\Service;

use App\Entity\BacSaison;
use App\Entity\Culture;
use App\Entity\Saison;
use App\Entity\Utilisateur;
use App\Repository\BacRepository;
use App\Repository\BacSaisonRepository;
use App\Repository\CultureRepository;
use App\Repository\SaisonRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Cycle de vie des saisons du potager : démarrage d'une nouvelle saison avec
 * report. Clôture la saison active courante, persiste la nouvelle saison,
 * recopie la géométrie des bacs non archivés dans de nouveaux BacSaison, et
 * reporte les cultures pérennes encore en place.
 *
 * La grainothèque est hors saison (inchangée) ; les semis et cultures annuelles
 * ne sont pas reportés.
 */
final class SaisonCycleService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SaisonRepository $saisons,
        private readonly BacRepository $bacs,
        private readonly BacSaisonRepository $bacSaisons,
        private readonly CultureRepository $cultures,
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
        //    On indexe les nouveaux snapshots par id de Bac pour le report des pérennes.
        $newSnapshotsByBac = [];
        foreach ($this->bacs->findActiveByUser($user) as $bac) {
            $newSnapshotsByBac[$bac->getId()] = $this->createSnapshot($user, $bac, $newSaison);
        }

        // 4. Reporter les cultures pérennes « en_place » de la saison clôturée vers
        //    le BacSaison correspondant (même Bac) de la nouvelle saison. Les bacs
        //    archivés n'ont pas de nouveau snapshot → leurs cultures ne sont pas reportées.
        if (null !== $active && $active !== $newSaison) {
            foreach ($this->cultures->findEnPlacePerennes($active) as $parent) {
                $bac = $parent->getBacSaison()?->getBac();
                $target = null !== $bac ? ($newSnapshotsByBac[$bac->getId()] ?? null) : null;
                if (null === $target) {
                    continue;
                }
                $this->reportPerenne($user, $parent, $newSaison, $target);
            }
        }

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

    /**
     * Crée (sans flush) la culture reportée d'une pérenne dans la nouvelle saison :
     * même position/emprise/type, statut « en_place », perenne conservé,
     * datePlantation d'origine conservée, lignage via parentCulture. Le semis
     * d'origine (ancienne saison) n'est pas reporté.
     */
    private function reportPerenne(Utilisateur $user, Culture $parent, Saison $newSaison, BacSaison $target): void
    {
        $report = (new Culture())
            ->setUtilisateur($user)
            ->setSaison($newSaison)
            ->setBacSaison($target)
            ->setGraineType($parent->getGraineType())
            ->setName((string) $parent->getName())
            ->setPosX($parent->getPosX())
            ->setPosY($parent->getPosY())
            ->setLargeurCases($parent->getLargeurCases())
            ->setHauteurCases($parent->getHauteurCases())
            ->setDatePlantation($parent->getDatePlantation())
            ->setStatut(Culture::STATUT_EN_PLACE)
            ->setPerenne(true)
            ->setParentCulture($parent);

        $this->em->persist($report);
    }
}
