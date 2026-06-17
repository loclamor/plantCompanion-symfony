<?php

namespace App\Repository;

use App\Entity\Action;
use App\Entity\Utilisateur;
use App\Entity\Vegetable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Action>
 */
class ActionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Action::class);
    }

    /**
     * @return Action[] Les interventions du seul utilisateur donné, plus récentes d'abord.
     */
    public function findByUser(Utilisateur $user): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('a.date', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Action[] Les interventions d'une plante, plus récentes d'abord.
     */
    public function findByVegetable(Vegetable $vegetable): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.vegetable = :vegetable')
            ->setParameter('vegetable', $vegetable)
            ->orderBy('a.date', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    //    /**
    //     * @return Action[] Returns an array of Action objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('a.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Action
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
