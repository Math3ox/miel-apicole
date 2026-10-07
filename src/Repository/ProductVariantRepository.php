<?php

namespace App\Repository;

use App\Entity\ProductVariant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProductVariantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductVariant::class);
    }

    /**
     * Toutes les variantes pour la page Stock, triées par produit puis poids.
     * $filter : null (tout), 'bas' (1 à 5 pots) ou 'rupture' (0).
     *
     * @return ProductVariant[]
     */
    public function findForStockPage(?string $filter = null): array
    {
        $qb = $this->createQueryBuilder('v')
            ->join('v.product', 'p')->addSelect('p')
            ->orderBy('p.name', 'ASC')
            ->addOrderBy('v.weight', 'ASC');

        if ($filter === 'rupture') {
            $qb->andWhere('v.stock <= 0');
        } elseif ($filter === 'bas') {
            $qb->andWhere('v.stock > 0 AND v.stock <= :seuil')
               ->setParameter('seuil', ProductVariant::LOW_STOCK_THRESHOLD);
        }

        return $qb->getQuery()->getResult();
    }

    // nombre de variantes en stock bas et en rupture, pour les compteurs
    public function countStockAlerts(): array
    {
        $row = $this->createQueryBuilder('v')
            ->select('SUM(CASE WHEN v.stock <= 0 THEN 1 ELSE 0 END) AS rupture')
            ->addSelect('SUM(CASE WHEN v.stock > 0 AND v.stock <= :seuil THEN 1 ELSE 0 END) AS bas')
            ->setParameter('seuil', ProductVariant::LOW_STOCK_THRESHOLD)
            ->getQuery()
            ->getSingleResult();

        return ['rupture' => (int) $row['rupture'], 'bas' => (int) $row['bas']];
    }
}
