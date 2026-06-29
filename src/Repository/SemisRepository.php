<?php

namespace App\Repository;

use App\Entity\Graine;
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

    /**
     * Semis éligibles à une action en lot : scope user + saison + graineType,
     * filtrés par graine (ou « sans lot » si null) et par statuts autorisés,
     * triés du plus ancien au plus récent (FIFO).
     *
     * @param string[] $statuts
     *
     * @return Semis[]
     */
    public function findForBatchAction(Utilisateur $user, Saison $saison, GraineType $graineType, ?Graine $graine, array $statuts): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.utilisateur = :user')->setParameter('user', $user)
            ->andWhere('s.saison = :saison')->setParameter('saison', $saison)
            ->andWhere('s.graineType = :gt')->setParameter('gt', $graineType)
            ->andWhere('s.statut IN (:statuts)')->setParameter('statuts', $statuts);

        if (null !== $graine) {
            $qb->join('s.graineLot', 'l')->andWhere('l.graine = :graine')->setParameter('graine', $graine);
        } else {
            $qb->andWhere('s.graineLot IS NULL');
        }

        return $qb->orderBy('s.dateSemis', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
