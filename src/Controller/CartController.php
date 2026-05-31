<?php

namespace App\Controller;

use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/panier', name: 'app_cart_')]
class CartController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(CartService $cart): Response
    {
        return $this->render('cart/index.html.twig', [
            'items' => $cart->getFullCart(),
            'total' => $cart->getTotal(),
        ]);
    }

    #[Route('/ajouter', name: 'add', methods: ['POST'])]
    public function add(Request $request, CartService $cart): Response
    {
        $variantId = (int) $request->request->get('variant_id');
        $quantity  = max(1, (int) $request->request->get('quantity', 1));

        if ($variantId > 0) {
            $cart->add($variantId, $quantity);
            $this->addFlash('success', 'Produit ajouté au panier.');
        }

        $referer = $request->headers->get('referer');
        return $this->redirect($referer ?: $this->generateUrl('app_cart_index'));
    }

    #[Route('/modifier', name: 'update', methods: ['POST'])]
    public function update(Request $request, CartService $cart): Response
    {
        $variantId = (int) $request->request->get('variant_id');
        $quantity  = (int) $request->request->get('quantity');

        if ($variantId > 0) {
            $cart->update($variantId, $quantity);
        }

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/supprimer/{variantId}', name: 'remove', methods: ['POST'])]
    public function remove(int $variantId, CartService $cart): Response
    {
        $cart->remove($variantId);
        $this->addFlash('success', 'Article retiré du panier.');
        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/vider', name: 'clear', methods: ['POST'])]
    public function clear(CartService $cart): Response
    {
        $cart->clear();
        $this->addFlash('success', 'Panier vidé.');
        return $this->redirectToRoute('app_cart_index');
    }
}
