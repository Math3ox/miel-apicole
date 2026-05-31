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

    public function add(int $variantId, int $quantity = 1): void
    {
        $cart = $this->getRawCart();
        $cart[$variantId] = ($cart[$variantId] ?? 0) + $quantity;
        $this->save($cart);
    }

    public function remove(int $variantId): void
    {
        $cart = $this->getRawCart();
        unset($cart[$variantId]);
        $this->save($cart);
    }

    public function update(int $variantId, int $quantity): void
    {
        $cart = $this->getRawCart();
        if ($quantity <= 0) {
            unset($cart[$variantId]);
        } else {
            $cart[$variantId] = $quantity;
        }
        $this->save($cart);
    }

    public function clear(): void
    {
        $this->save([]);
    }

    public function getCount(): int
    {
        return (int) array_sum($this->getRawCart());
    }

    /** @return array<array{variant: \App\Entity\ProductVariant, quantity: int, subtotal: float}> */
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
