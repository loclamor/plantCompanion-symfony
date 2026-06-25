<?php

namespace App\Repository;

use App\Entity\Bac;
use App\Entity\BacSaison;
use App\Entity\Saison;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BacSaison>
 */
class BacSaisonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BacSaison::class);
    }

    /**
     * @return BacSaison[] Les snapshots d'une saison donnée (du même utilisateur).
     */
    public function findByUserAndSaison(Utilisateur $user, Saison $saison): array
    {
        return $this->createQueryBuilder('bs')
            ->join('bs.bac', 'b')
            ->andWhere('bs.utilisateur = :user')
            ->andWhere('bs.saison = :saison')
            ->setParameter('user', $user)
            ->setParameter('saison', $saison)
            ->orderBy('b.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Le dernier snapshot connu d'un bac (saison la plus récente), pour recopier
     * sa géométrie au démarrage d'une nouvelle saison. Null si jamais snapshoté.
     */
    public function findLastForBac(Bac $bac): ?BacSaison
    {
        return $this->createQueryBuilder('bs')
            ->join('bs.saison', 's')
            ->andWhere('bs.bac = :bac')
            ->setParameter('bac', $bac)
            ->orderBy('s.annee', 'DESC')
            ->addOrderBy('bs.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
