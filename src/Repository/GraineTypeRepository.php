<?php

namespace App\Repository;

use App\Entity\GraineType;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GraineType>
 */
class GraineTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GraineType::class);
    }

    /**
     * @return GraineType[] Les types de graines du seul utilisateur donné.
     */
    public function findByUser(Utilisateur $user): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Ids de $root et de tous ses descendants (hiérarchie récursive). Le nombre
     * de types par utilisateur étant faible, on charge tout en mémoire et on
     * parcourt en largeur la map parent → enfants. Sert au filtre de recherche
     * (inclure les graines des sous-types) et à la garde anti-cycle.
     *
     * @return int[] ids, racine incluse
     */
    public function descendantIds(Utilisateur $user, GraineType $root): array
    {
        // map[parentId] => GraineType[] des enfants directs
        $childrenByParent = [];
        foreach ($this->findByUser($user) as $type) {
            $parentId = $type->getParent()?->getId();
            if (null !== $parentId) {
                $childrenByParent[$parentId][] = $type;
            }
        }

        $ids = [];
        $queue = [$root];
        while ([] !== $queue) {
            $current = array_shift($queue);
            $id = $current->getId();
            if (null === $id || \in_array($id, $ids, true)) {
                continue; // garde contre un cycle éventuel
            }
            $ids[] = $id;
            foreach ($childrenByParent[$id] ?? [] as $child) {
                $queue[] = $child;
            }
        }

        return $ids;
    }

    /**
     * Type de graine de l'utilisateur portant ce préfixe de code, en excluant
     * éventuellement un id (l'entité en cours d'édition) — valide l'unicité.
     */
    public function findOneByCode(Utilisateur $user, string $code, ?int $excludeId = null): ?GraineType
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
}
