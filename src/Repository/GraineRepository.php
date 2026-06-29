<?php

namespace App\Repository;

use App\Entity\Graine;
use App\Entity\GraineType;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Graine>
 */
class GraineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Graine::class);
    }

    /**
     * @return Graine[] Les graines du seul utilisateur donné.
     */
    public function findByUser(Utilisateur $user): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('g.code', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Graine[] Les graines d'un type donné (du même utilisateur).
     */
    public function findByUserAndGraineType(Utilisateur $user, GraineType $graineType): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.utilisateur = :user')
            ->andWhere('g.graineType = :graineType')
            ->setParameter('user', $user)
            ->setParameter('graineType', $graineType)
            ->orderBy('g.code', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Graine de l'utilisateur portant ce code, en excluant éventuellement un id
     * (l'entité en cours d'édition) — valide l'unicité du code.
     */
    public function findOneByCode(Utilisateur $user, string $code, ?int $excludeId = null): ?Graine
    {
        $qb = $this->createQueryBuilder('g')
            ->andWhere('g.utilisateur = :user')
            ->andWhere('g.code = :code')
            ->setParameter('user', $user)
            ->setParameter('code', $code)
            ->setMaxResults(1)
        ;

        if (null !== $excludeId) {
            $qb->andWhere('g.id != :id')->setParameter('id', $excludeId);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Code suggéré pour une nouvelle graine de ce type : préfixe + (plus grand
     * suffixe numérique des codes existants du type +1, défaut 1).
     */
    public function nextCodeForType(Utilisateur $user, GraineType $graineType): string
    {
        $prefix = (string) $graineType->getCode();
        $graines = $this->findByUserAndGraineType($user, $graineType);

        $max = 0;
        $len = \strlen($prefix);
        foreach ($graines as $graine) {
            $code = (string) $graine->getCode();
            if (str_starts_with($code, $prefix)) {
                $suffix = substr($code, $len);
                if (ctype_digit($suffix) && '' !== $suffix) {
                    $max = max($max, (int) $suffix);
                }
            }
        }

        return $prefix.($max + 1);
    }
}
