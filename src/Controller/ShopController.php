<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\ReviewRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ShopController extends AbstractController
{
    #[Route('/nos-miels', name: 'app_shop_index', methods: ['GET'])]
    public function index(
        Request $request,
        ProductRepository $productRepo,
        CategoryRepository $categoryRepo,
    ): Response {
        $categorySlug = $request->query->get('categorie');
        $sort         = $request->query->get('tri', 'default');

        if (!in_array($sort, ['default', 'price_asc', 'price_desc', 'name'])) {
            $sort = 'default';
        }

        return $this->render('shop/index.html.twig', [
            'products'        => $productRepo->findForCatalogue($categorySlug, $sort),
            'categories'      => $categoryRepo->findBy([], ['name' => 'ASC']),
            'currentCategory' => $categorySlug,
            'currentSort'     => $sort,
        ]);
    }

    #[Route('/nos-miels/{slug}', name: 'app_shop_show', methods: ['GET'])]
    public function show(
        string $slug,
        ProductRepository $productRepo,
        ReviewRepository $reviewRepo,
    ): Response {
        $product = $productRepo->findBySlugWithRelations($slug);

        if (!$product) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        $reviews  = $reviewRepo->findApprovedByProduct($product);
        $avgRating = count($reviews) > 0
            ? array_sum(array_map(fn ($r) => $r->getRating(), $reviews)) / count($reviews)
            : null;

        return $this->render('shop/show.html.twig', [
            'product'   => $product,
            'reviews'   => $reviews,
            'avgRating' => $avgRating,
        ]);
    }
}
