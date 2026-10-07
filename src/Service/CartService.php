<?php

namespace App\Service;

use App\Repository\ProductVariantRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class CartService
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ProductVariantRepository $variantRepository,
    ) {}

    // renvoie false si le stock ne suffit pas (la quantité est alors plafonnée au stock)
    public function add(int $variantId, int $quantity = 1): bool
    {
        $cart = $this->getRawCart();
        // si le produit est deja dans le panier on ajoute la quantité, sinon on part de 0
        return $this->update($variantId, ($cart[$variantId] ?? 0) + $quantity);
    }

    public function remove(int $variantId): void
    {
        $cart = $this->getRawCart();
        unset($cart[$variantId]);
        $this->save($cart);
    }

    // renvoie false si le stock ne suffit pas (la quantité est alors plafonnée au stock)
    public function update(int $variantId, int $quantity): bool
    {
        $cart    = $this->getRawCart();
        $variant = $this->variantRepository->find($variantId);
        $stock   = $variant?->getStock() ?? 0;
        $kept    = min($quantity, $stock);

        if ($kept <= 0) {
            unset($cart[$variantId]);
        } else {
            $cart[$variantId] = $kept;
        }
        $this->save($cart);

        return $quantity <= $stock;
    }

    public function clear(): void
    {
        $this->save([]);
    }

    public function getCount(): int
    {
        return (int) array_sum($this->getRawCart());
    }

    public function getFullCart(): array
    {
        $items = [];
        foreach ($this->getRawCart() as $variantId => $quantity) {
            $variant = $this->variantRepository->find($variantId);
            if ($variant !== null) {
                $items[] = [
                    'variant'  => $variant,
                    'quantity' => $quantity,
                    'subtotal' => (float) $variant->getPrice() * $quantity,
                ];
            }
        }
        return $items;
    }

    public function getTotal(): float
    {
        return array_sum(array_column($this->getFullCart(), 'subtotal'));
    }

    public function getRawCart(): array
    {
        return $this->requestStack->getSession()->get('cart', []);
    }

    private function save(array $cart): void
    {
        $this->requestStack->getSession()->set('cart', $cart);
    }
}
