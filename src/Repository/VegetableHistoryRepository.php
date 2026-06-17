<?php

namespace App\Repository;

use App\Entity\Vegetable;
use App\Entity\VegetableHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VegetableHistory>
 */
class VegetableHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VegetableHistory::class);
    }

    /**
     * @return VegetableHistory[] L'historique d'une plante, plus récent d'abord.
     */
    public function findByVegetable(Vegetable $vegetable): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.entity = :vegetable')
            ->setParameter('vegetable', $vegetable)
            ->orderBy('h.date', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    //    /**
    //     * @return VegetableHistory[] Returns an array of VegetableHistory objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('v')
    //            ->andWhere('v.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('v.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?VegetableHistory
    //    {
    //        return $this->createQueryBuilder('v')
    //            ->andWhere('v.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
