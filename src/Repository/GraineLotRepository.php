<?php

namespace App\Repository;

use App\Entity\Graine;
use App\Entity\GraineLot;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GraineLot>
 */
class GraineLotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GraineLot::class);
    }

    /**
     * @return GraineLot[] Les lots du seul utilisateur donné.
     */
    public function findByUser(Utilisateur $user): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('l.dateAcquisition', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return GraineLot[] Les lots d'une graine donnée (du même utilisateur).
     */
    public function findByUserAndGraine(Utilisateur $user, Graine $graine): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.utilisateur = :user')
            ->andWhere('l.graine = :graine')
            ->setParameter('user', $user)
            ->setParameter('graine', $graine)
            ->orderBy('l.dateAcquisition', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }
}
