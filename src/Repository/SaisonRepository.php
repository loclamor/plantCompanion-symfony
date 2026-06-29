<?php

namespace App\Repository;

use App\Entity\Saison;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Saison>
 */
class SaisonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Saison::class);
    }

    /**
     * @return Saison[] Les saisons du seul utilisateur donné (plus récentes d'abord).
     */
    public function findByUser(Utilisateur $user): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('s.annee', 'DESC')
            ->addOrderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * La saison active de l'utilisateur (au plus une), sinon null.
     */
    public function findActiveForUser(Utilisateur $user): ?Saison
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.utilisateur = :user')
            ->andWhere('s.statut = :statut')
            ->setParameter('user', $user)
            ->setParameter('statut', Saison::STATUT_ACTIVE)
            ->orderBy('s.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
