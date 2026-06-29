<?php

namespace App\Repository;

use App\Entity\Bac;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Bac>
 */
class BacRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bac::class);
    }

    /**
     * @return Bac[] Les bacs du seul utilisateur donné (actifs d'abord, par nom).
     */
    public function findByUser(Utilisateur $user): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('b.archived', 'ASC')
            ->addOrderBy('b.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Bac[] Les bacs non archivés de l'utilisateur (candidats au report).
     */
    public function findActiveByUser(Utilisateur $user): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.utilisateur = :user')
            ->andWhere('b.archived = false')
            ->setParameter('user', $user)
            ->orderBy('b.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
