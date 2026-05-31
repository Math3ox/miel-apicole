<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'app_sitemap', defaults: ['_format' => 'xml'], methods: ['GET'])]
    public function index(
        ProductRepository $productRepo,
        CategoryRepository $categoryRepo,
    ): Response {
        $urls = [];

        // Pages statiques.
        $urls[] = ['loc' => $this->abs('app_home'), 'priority' => '1.0'];
        $urls[] = ['loc' => $this->abs('app_shop_index'), 'priority' => '0.9'];

        // Catégories (filtres du catalogue).
        foreach ($categoryRepo->findAll() as $category) {
            $urls[] = [
                'loc'      => $this->generateUrl('app_shop_index', ['categorie' => $category->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'priority' => '0.6',
            ];
        }

        // Fiches produits.
        foreach ($productRepo->findAll() as $product) {
            $urls[] = [
                'loc'      => $this->generateUrl('app_shop_show', ['slug' => $product->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'lastmod'  => $product->getCreatedAt()?->format('Y-m-d'),
                'priority' => '0.8',
            ];
        }

        $response = new Response(
            $this->renderView('sitemap/index.xml.twig', ['urls' => $urls]),
        );
        $response->headers->set('Content-Type', 'application/xml');

        return $response;
    }

    private function abs(string $route): string
    {
        return $this->generateUrl($route, [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
