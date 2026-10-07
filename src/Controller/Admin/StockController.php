<?php

namespace App\Controller\Admin;

use App\Entity\ProductVariant;
use App\Repository\ProductVariantRepository;
use App\Repository\StockMovementRepository;
use App\Service\StockManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/stock', name: 'admin_stock_')]
class StockController extends AbstractController
{
    // actions possibles depuis la page Stock
    public const ACTIONS = [
        'restock'   => 'Réassort (+)',
        'loss'      => 'Perte / casse (−)',
        'inventory' => 'Inventaire (= quantité comptée)',
    ];

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, ProductVariantRepository $variantRepo): Response
    {
        $filter = $request->query->get('filtre');
        if (!in_array($filter, ['bas', 'rupture'], true)) {
            $filter = null;
        }

        return $this->render('admin/stock/index.html.twig', [
            'variants' => $variantRepo->findForStockPage($filter),
            'alerts'   => $variantRepo->countStockAlerts(),
            'filter'   => $filter,
            'actions'  => self::ACTIONS,
            'seuil'    => ProductVariant::LOW_STOCK_THRESHOLD,
        ]);
    }

    #[Route('/{id}/mouvement', name: 'move', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function move(
        ProductVariant $variant,
        Request $request,
        EntityManagerInterface $em,
        StockManager $stock,
    ): Response {
        $back = $this->redirectToRoute('admin_stock_index', [
            'filtre'    => $request->request->get('filtre') ?: null,
            '_fragment' => 'variant-' . $variant->getId(),
        ]);

        if (!$this->isCsrfTokenValid('stock', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $back;
        }

        $action   = $request->request->get('action', '');
        $quantity = $request->request->get('quantity', '');
        // mb_scrub : un texte mal encodé ne doit pas faire planter l'enregistrement
        $comment  = mb_substr(mb_scrub(trim($request->request->get('comment', ''))), 0, 255) ?: null;
        $label    = sprintf('%s %d g', $variant->getProduct()->getName(), $variant->getWeight());

        if (!array_key_exists($action, self::ACTIONS)) {
            $this->addFlash('error', 'Action inconnue.');
            return $back;
        }
        if (!ctype_digit((string) $quantity) || ((int) $quantity === 0 && $action !== 'inventory')) {
            $this->addFlash('error', 'Indiquez un nombre de pots (entier positif).');
            return $back;
        }
        $quantity = (int) $quantity;

        try {
            $movement = $em->wrapInTransaction(function () use ($variant, $action, $quantity, $comment, $stock) {
                $stock->lock($variant);

                $delta = match ($action) {
                    'restock'   => $quantity,
                    'loss'      => -$quantity,
                    'inventory' => $quantity - $variant->getStock(),
                };

                return $delta === 0 ? null : $stock->move($variant, $delta, $action, $this->getUser(), null, $comment);
            });
        } catch (\DomainException) {
            $this->addFlash('error', sprintf('%s : impossible de retirer %d pots, il n\'y en a que %d.', $label, $quantity, $variant->getStock()));
            return $back;
        }

        if ($movement === null) {
            $this->addFlash('info', sprintf('%s : le stock était déjà de %d, rien à changer.', $label, $variant->getStock()));
        } else {
            $this->addFlash('success', sprintf('%s : %+d → %d en stock.', $label, $movement->getQuantity(), $variant->getStock()));
        }

        return $back;
    }

    #[Route('/historique', name: 'history', methods: ['GET'])]
    public function history(
        Request $request,
        StockMovementRepository $movementRepo,
        ProductVariantRepository $variantRepo,
    ): Response {
        $variant = $request->query->get('variante') ? $variantRepo->find((int) $request->query->get('variante')) : null;

        return $this->render('admin/stock/history.html.twig', [
            'movements' => $movementRepo->findLatest($variant),
            'variant'   => $variant,
        ]);
    }
}
