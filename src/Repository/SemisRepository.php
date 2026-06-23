<?php

namespace App\Repository;

use App\Entity\GraineType;
use App\Entity\Saison;
use App\Entity\Semis;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Semis>
 */
class SemisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Semis::class);
    }

    /**
     * @return Semis[]
     */
    public function findByUser(Utilisateur $user): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('s.dateSemis', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    private function filteredQb(Utilisateur $user, ?Saison $saison, ?string $statut, ?GraineType $graineType): QueryBuilder
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.utilisateur = :user')
            ->setParameter('user', $user);

        if (null !== $saison) {
            $qb->andWhere('s.saison = :saison')->setParameter('saison', $saison);
        }
        if (null !== $statut && '' !== $statut) {
            $qb->andWhere('s.statut = :statut')->setParameter('statut', $statut);
        }
        if (null !== $graineType) {
            $qb->andWhere('s.graineType = :graineType')->setParameter('graineType', $graineType);
        }

        return $qb;
    }

    /**
     * @return Semis[]
     */
    public function findByUserFiltered(Utilisateur $user, ?Saison $saison, ?string $statut, ?GraineType $graineType): array
    {
        return $this->filteredQb($user, $saison, $statut, $graineType)
            ->orderBy('s.dateSemis', 'DESC')
            ->addOrderBy('s.graineType', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
