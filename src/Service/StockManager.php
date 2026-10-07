<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\ProductVariant;
use App\Entity\StockMovement;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

// seul endroit qui modifie le stock : chaque changement laisse une ligne dans l'historique
class StockManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    // relit la variante en base en la verrouillant (SELECT ... FOR UPDATE) jusqu'à la fin
    // de la transaction : deux opérations simultanées ne peuvent pas se marcher dessus.
    // A appeler dans $em->wrapInTransaction(), avant toute modification de la variante.
    public function lock(ProductVariant $variant): void
    {
        if ($variant->getId() !== null) {
            $this->em->refresh($variant, LockMode::PESSIMISTIC_WRITE);
        }
    }

    // applique une variation (+ ou -) et l'enregistre dans l'historique ; le flush est laissé à l'appelant
    public function move(
        ProductVariant $variant,
        int $quantity,
        string $type,
        ?User $user = null,
        ?Order $order = null,
        ?string $comment = null,
    ): StockMovement {
        if (!array_key_exists($type, StockMovement::TYPES)) {
            throw new \InvalidArgumentException(sprintf('Type de mouvement inconnu : %s', $type));
        }

        $newStock = ($variant->getStock() ?? 0) + $quantity;
        if ($newStock < 0) {
            throw new \DomainException(sprintf(
                'Stock insuffisant pour %s %d g : %d en stock.',
                $variant->getProduct()?->getName(),
                $variant->getWeight(),
                $variant->getStock(),
            ));
        }

        $variant->setStock($newStock);

        $movement = new StockMovement($variant, $quantity, $type);
        $movement->setUser($user);
        $movement->setOrder($order);
        $movement->setComment($comment);
        $this->em->persist($movement);

        return $movement;
    }
}
