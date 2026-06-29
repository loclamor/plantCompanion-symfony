<?php

namespace App\Repository;

use App\Entity\Graine;
use App\Entity\GraineLot;
use App\Entity\GraineType;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Graine>
 */
class GraineRepository extends ServiceEntityRepository
{
    /** Seuil (inclus) sous lequel le stock est considéré « faible » ; au-delà « en quantité ». */
    public const SEUIL_FAIBLE = 10;

    /** Champs de tri autorisés (clé = paramètre public, valeur = expression DQL). */
    public const SORTABLE = [
        'code' => 'g.code',
        'name' => 'g.name',
        'stock' => 'stock',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Graine::class);
    }

    /**
     * QueryBuilder filtré, agrégeant le stock restant (somme des quantités
     * restantes des lots) via un LEFT JOIN + GROUP BY g.id. L'alias `stock`
     * (HIDDEN) sert au filtre stock (HAVING) et au tri ; getResult() retourne
     * donc bien des Graine[].
     *
     * @param int[]|null  $graineTypeIds ids de types acceptés (type filtré + ses descendants) ; null = pas de filtre
     * @param string|null $stockStatus   '', 'rachat' (=0), 'faible' (1..seuil), 'ok' (>seuil)
     */
    private function filteredQb(Utilisateur $user, ?string $q, ?array $graineTypeIds, ?string $stockStatus): QueryBuilder
    {
        $qb = $this->createQueryBuilder('g')
            ->leftJoin('g.graineType', 'gt')
            ->leftJoin(GraineLot::class, 'l', 'ON', 'l.graine = g')
            ->addSelect('COALESCE(SUM(l.quantiteRestante), 0) AS HIDDEN stock')
            ->andWhere('g.utilisateur = :user')
            ->setParameter('user', $user)
            ->groupBy('g.id');

        if (null !== $q && '' !== $q) {
            $qb->andWhere('g.code LIKE :q OR g.name LIKE :q OR gt.name LIKE :q')
                ->setParameter('q', '%'.$q.'%');
        }
        if (null !== $graineTypeIds && [] !== $graineTypeIds) {
            $qb->andWhere('g.graineType IN (:graineTypeIds)')->setParameter('graineTypeIds', $graineTypeIds);
        }

        switch ($stockStatus) {
            case 'rachat':
                $qb->having('stock = 0');
                break;
            case 'faible':
                $qb->having('stock >= 1 AND stock <= :seuil')->setParameter('seuil', self::SEUIL_FAIBLE);
                break;
            case 'ok':
                $qb->having('stock > :seuil')->setParameter('seuil', self::SEUIL_FAIBLE);
                break;
        }

        return $qb;
    }

    /**
     * @return Graine[]
     */
    public function findByUserFiltered(Utilisateur $user, ?string $q, ?array $graineTypeIds, ?string $stockStatus, string $sort, string $dir, int $limit, int $offset): array
    {
        $field = self::SORTABLE[$sort] ?? 'g.code';
        $direction = 'desc' === strtolower($dir) ? 'DESC' : 'ASC';

        return $this->filteredQb($user, $q, $graineTypeIds, $stockStatus)
            ->orderBy($field, $direction)
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Nombre de graines correspondant aux filtres. Le GROUP BY + HAVING empêche
     * un COUNT direct : on sélectionne g.id (une ligne par graine) et on compte
     * les lignes côté PHP.
     */
    public function countByUserFiltered(Utilisateur $user, ?string $q, ?array $graineTypeIds, ?string $stockStatus): int
    {
        $rows = $this->filteredQb($user, $q, $graineTypeIds, $stockStatus)
            ->select('g.id')
            ->addSelect('COALESCE(SUM(l.quantiteRestante), 0) AS HIDDEN stock')
            ->getQuery()
            ->getScalarResult()
        ;

        return \count($rows);
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
