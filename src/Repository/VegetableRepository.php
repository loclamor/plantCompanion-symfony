<?php

namespace App\Repository;

use App\Entity\Group;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\Vegetable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Vegetable>
 */
class VegetableRepository extends ServiceEntityRepository
{
    /** Champs de tri autorisés (clé = paramètre public, valeur = propriété). */
    public const SORTABLE = [
        'name' => 'name',
        'creationDate' => 'creationDate',
        'addDate' => 'addDate',
        'rusticite' => 'rusticite',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vegetable::class);
    }

    /**
     * @return Vegetable[] Les plantes du seul utilisateur donné.
     */
    public function findByUser(Utilisateur $user): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('v.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    private function filteredQb(Utilisateur $user, ?Group $group, ?string $q, ?Type $type): QueryBuilder
    {
        $qb = $this->createQueryBuilder('v')
            ->andWhere('v.utilisateur = :user')
            ->setParameter('user', $user);

        if (null !== $group) {
            $qb->andWhere('v.group = :group')->setParameter('group', $group);
        }
        if (null !== $q && '' !== $q) {
            $qb->andWhere('v.name LIKE :q')->setParameter('q', '%'.$q.'%');
        }
        if (null !== $type) {
            $qb->andWhere('v.type = :type')->setParameter('type', $type);
        }

        return $qb;
    }

    /**
     * @return Vegetable[]
     */
    public function findByUserFiltered(Utilisateur $user, ?Group $group, ?string $q, ?Type $type, string $sort, string $dir, int $limit, int $offset): array
    {
        $field = self::SORTABLE[$sort] ?? 'name';
        $direction = 'desc' === strtolower($dir) ? 'DESC' : 'ASC';

        return $this->filteredQb($user, $group, $q, $type)
            ->orderBy('v.'.$field, $direction)
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult()
        ;
    }

    public function countByUserFiltered(Utilisateur $user, ?Group $group, ?string $q, ?Type $type): int
    {
        return (int) $this->filteredQb($user, $group, $q, $type)
            ->select('COUNT(v.id)')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    //    /**
    //     * @return Vegetable[] Returns an array of Vegetable objects
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

    //    public function findOneBySomeField($value): ?Vegetable
    //    {
    //        return $this->createQueryBuilder('v')
    //            ->andWhere('v.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
