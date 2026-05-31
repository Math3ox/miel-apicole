<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Repository\OrderRepository;
use App\Service\InvoiceGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/commandes', name: 'admin_order_')]
class OrderController extends AbstractController
{
    public const STATUSES = [
        'pending'   => 'En attente',
        'paid'      => 'Payée',
        'shipped'   => 'Expédiée',
        'delivered' => 'Livrée',
        'cancelled' => 'Annulée',
    ];

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, OrderRepository $orderRepo): Response
    {
        $status = $request->query->get('status');
        $criteria = array_key_exists($status, self::STATUSES) ? ['status' => $status] : [];

        return $this->render('admin/order/index.html.twig', [
            'orders'       => $orderRepo->findBy($criteria, ['createdAt' => 'DESC']),
            'statuses'     => self::STATUSES,
            'activeStatus' => $criteria ? $status : null,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Order $order): Response
    {
        return $this->render('admin/order/show.html.twig', [
            'order'    => $order,
            'statuses' => self::STATUSES,
        ]);
    }

    #[Route('/{id}/statut', name: 'update_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateStatus(
        Order $order,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('update_status_' . $order->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('admin_order_show', ['id' => $order->getId()]);
        }

        $newStatus = $request->request->get('status', '');
        if (!array_key_exists($newStatus, self::STATUSES)) {
            $this->addFlash('error', 'Statut invalide.');
            return $this->redirectToRoute('admin_order_show', ['id' => $order->getId()]);
        }

        $order->setStatus($newStatus);
        $order->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', sprintf('Commande #%d : statut mis à jour en « %s ».', $order->getId(), self::STATUSES[$newStatus]));
        return $this->redirectToRoute('admin_order_show', ['id' => $order->getId()]);
    }

    #[Route('/{id}/facture', name: 'invoice', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function invoice(Order $order, InvoiceGenerator $invoices): Response
    {
        return new Response(
            $invoices->generate($order),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $invoices->filename($order)),
            ],
        );
    }
}
