<?php

namespace App\Repository;

use App\Entity\Rempotage;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Rempotage>
 */
class RempotageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rempotage::class);
    }

    /**
     * @return Rempotage[]
     */
    public function findByUser(Utilisateur $user): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('r.date', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
