<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\Review;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    public function findApprovedByProduct(Product $product): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.user', 'u')->addSelect('u')
            ->andWhere('r.product = :product')->setParameter('product', $product)
            ->andWhere('r.isApprouved = true')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
