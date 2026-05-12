<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findForCatalogue(?string $categorySlug = null, string $sort = 'default'): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')->addSelect('c')
            ->leftJoin('p.productVariants', 'v')->addSelect('v');

        if ($categorySlug) {
            $qb->andWhere('c.slug = :slug')->setParameter('slug', $categorySlug);
        }

        match ($sort) {
            'price_asc'  => $qb->orderBy('p.price', 'ASC'),
            'price_desc' => $qb->orderBy('p.price', 'DESC'),
            'name'       => $qb->orderBy('p.name', 'ASC'),
            default      => $qb->orderBy('p.createdAt', 'DESC'),
        };

        return $qb->getQuery()->getResult();
    }

    public function findBySlugWithRelations(string $slug): ?Product
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')->addSelect('c')
            ->leftJoin('p.productVariants', 'v')->addSelect('v')
            ->andWhere('p.slug = :slug')->setParameter('slug', $slug)
            ->orderBy('v.weight', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
