<?php

namespace App\Repository;

use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductVariant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OrderItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderItem::class);
    }

    // un produit deja commandé ne doit pas etre supprimé : les commandes et factures pointent dessus
    public function isProductOrdered(Product $product): bool
    {
        return (bool) $this->createQueryBuilder('oi')
            ->select('COUNT(oi.id)')
            ->join('oi.productVariant', 'v')
            ->andWhere('v.product = :product')->setParameter('product', $product)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function isVariantOrdered(ProductVariant $variant): bool
    {
        return $this->count(['productVariant' => $variant]) > 0;
    }
}
