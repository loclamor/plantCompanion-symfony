<?php

namespace App\Repository;

use App\Entity\BacSaison;
use App\Entity\Culture;
use App\Entity\Saison;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Culture>
 */
class CultureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Culture::class);
    }

    private function filteredQb(Utilisateur $user, ?Saison $saison, ?BacSaison $bacSaison, ?string $statut): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.utilisateur = :user')
            ->setParameter('user', $user);

        if (null !== $saison) {
            $qb->andWhere('c.saison = :saison')->setParameter('saison', $saison);
        }
        if (null !== $bacSaison) {
            $qb->andWhere('c.bacSaison = :bacSaison')->setParameter('bacSaison', $bacSaison);
        }
        if (null !== $statut && '' !== $statut) {
            $qb->andWhere('c.statut = :statut')->setParameter('statut', $statut);
        }

        return $qb;
    }

    /**
     * @return Culture[]
     */
    public function findByUserAndSaison(Utilisateur $user, Saison $saison): array
    {
        return $this->filteredQb($user, $saison, null, null)
            ->orderBy('c.datePlantation', 'DESC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Culture[]
     */
    public function findByUserFiltered(Utilisateur $user, ?Saison $saison, ?BacSaison $bacSaison, ?string $statut): array
    {
        return $this->filteredQb($user, $saison, $bacSaison, $statut)
            ->orderBy('c.datePlantation', 'DESC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Cultures pérennes encore en place d'une saison (pour le report de cycle).
     *
     * @return Culture[]
     */
    public function findEnPlacePerennes(Saison $saison): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.saison = :saison')->setParameter('saison', $saison)
            ->andWhere('c.perenne = true')
            ->andWhere('c.statut = :statut')->setParameter('statut', Culture::STATUT_EN_PLACE)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Cultures « en_place » d'un BacSaison (pour la validation de chevauchement).
     *
     * @return Culture[]
     */
    public function findEnPlaceByBacSaison(BacSaison $bacSaison): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.bacSaison = :bacSaison')->setParameter('bacSaison', $bacSaison)
            ->andWhere('c.statut = :statut')->setParameter('statut', Culture::STATUT_EN_PLACE)
            ->getQuery()
            ->getResult()
        ;
    }
}
