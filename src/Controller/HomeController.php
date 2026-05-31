<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(ProductRepository $productRepo): Response
    {
        return $this->render('home/index.html.twig', [
            'bestSellers' => $productRepo->findBy(['isBestSeller' => true], ['createdAt' => 'DESC'], 3),
            'promos'      => $productRepo->findBy(['isOnSale' => true], ['createdAt' => 'DESC'], 3),
        ]);
    }
}
