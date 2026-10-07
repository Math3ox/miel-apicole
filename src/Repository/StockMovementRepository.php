<?php

namespace App\Repository;

use App\Entity\ProductVariant;
use App\Entity\StockMovement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class StockMovementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockMovement::class);
    }

    /** @return StockMovement[] */
    public function findLatest(?ProductVariant $variant = null, int $limit = 200): array
    {
        $qb = $this->createQueryBuilder('m')
            ->join('m.variant', 'v')->addSelect('v')
            ->join('v.product', 'p')->addSelect('p')
            ->leftJoin('m.user', 'u')->addSelect('u')
            ->orderBy('m.createdAt', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setMaxResults($limit);

        if ($variant) {
            $qb->andWhere('m.variant = :variant')->setParameter('variant', $variant);
        }

        return $qb->getQuery()->getResult();
    }
}
