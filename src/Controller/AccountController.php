<?php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use App\Service\InvoiceGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/compte', name: 'app_account_')]
class AccountController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(OrderRepository $orderRepo): Response
    {
        $orders = $orderRepo->findByUser($this->getUser());

        return $this->render('account/index.html.twig', [
            'orders' => array_slice($orders, 0, 3),
            'orderCount' => count($orders),
        ]);
    }

    #[Route('/commandes', name: 'orders', methods: ['GET'])]
    public function orders(OrderRepository $orderRepo): Response
    {
        return $this->render('account/orders.html.twig', [
            'orders' => $orderRepo->findByUser($this->getUser()),
        ]);
    }

    #[Route('/commandes/{id}', name: 'order_show', methods: ['GET'])]
    public function orderShow(Order $order): Response
    {
        $this->denyUnlessOwner($order);

        return $this->render('account/order_show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/commandes/{id}/facture', name: 'invoice', methods: ['GET'])]
    public function invoice(Order $order, InvoiceGenerator $invoices): Response
    {
        $this->denyUnlessOwner($order);

        return new Response(
            $invoices->generate($order),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $invoices->filename($order)),
            ],
        );
    }

    private function denyUnlessOwner(Order $order): void
    {
        if ($order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
    }
}
