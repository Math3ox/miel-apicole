<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Service\CartService;
use App\Service\InvoiceGenerator;
use App\Service\MailerService;
use App\Service\StockManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// commande ouverte à tous : avec un compte, ou en invité (on demande alors juste un email)
#[Route('/commande', name: 'app_checkout_')]
class CheckoutController extends AbstractController
{
    // ids des commandes passées en invité dans cette session (pour leur page de confirmation)
    private const GUEST_ORDERS_KEY = 'guest_orders';

    // lien « Se connecter » de la page commande : la sécurité envoie vers la connexion
    // puis revient ici, et on renvoie sur la commande
    #[IsGranted('ROLE_USER')]
    #[Route('/connexion', name: 'login', methods: ['GET'])]
    public function login(): Response
    {
        return $this->redirectToRoute('app_checkout_index');
    }

    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        CartService $cart,
        EntityManagerInterface $em,
        MailerService $mailer,
        StockManager $stock,
    ): Response {
        $items = $cart->getFullCart();

        if (empty($items)) {
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('app_cart_index');
        }

        $errors = [];
        $user = $this->getUser();
        $data = [
            'email'      => '',
            'firstName'  => $user?->getFirstName() ?? '',
            'lastName'   => $user?->getLastName() ?? '',
            'street'     => '',
            'city'       => '',
            'postalCode' => '',
            'country'    => 'France',
        ];

        if ($request->isMethod('POST')) {
            $data = [
                'email'      => trim($request->request->get('email', '')),
                'firstName'  => trim($request->request->get('firstName', '')),
                'lastName'   => trim($request->request->get('lastName', '')),
                'street'     => trim($request->request->get('street', '')),
                'city'       => trim($request->request->get('city', '')),
                'postalCode' => trim($request->request->get('postalCode', '')),
                'country'    => trim($request->request->get('country', 'France')),
            ];

            if (!$this->isCsrfTokenValid('checkout', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token de sécurité invalide, veuillez réessayer.');
                return $this->redirectToRoute('app_checkout_index');
            }

            if (!$user && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Adresse email invalide : elle sert à vous envoyer la confirmation et la facture.';
            }

            foreach (['firstName', 'lastName', 'street', 'city', 'postalCode', 'country'] as $field) {
                if ($data[$field] === '') {
                    $errors[$field] = 'Ce champ est obligatoire.';
                }
            }

            if (empty($errors)) {
                $order = $em->wrapInTransaction(fn () => $this->createOrder($items, $data, $em, $stock));

                if ($order === null) {
                    // le stock a bougé depuis l'ajout au panier : on ajuste le panier et on prévient le client
                    foreach ($items as $item) {
                        $cart->update($item['variant']->getId(), $item['quantity']);
                    }
                    $this->addFlash('warning', 'Certains produits n\'ont plus assez de stock : votre panier a été ajusté, vérifiez-le avant de commander.');
                    return $this->redirectToRoute('app_cart_index');
                }

                $cart->clear();

                if ($order->isGuest()) {
                    $session = $request->getSession();
                    $session->set(self::GUEST_ORDERS_KEY, [...$session->get(self::GUEST_ORDERS_KEY, []), $order->getId()]);
                }

                try {
                    $mailer->sendOrderConfirmation($order);
                    $mailer->sendAdminOrderNotification($order);
                } catch (\Throwable) {
                }

                return $this->redirectToRoute('app_checkout_confirm', ['id' => $order->getId()]);
            }
        }

        return $this->render('checkout/index.html.twig', [
            'items'  => $items,
            'total'  => $cart->getTotal(),
            'data'   => $data,
            'errors' => $errors,
        ]);
    }

    // cree la commande a partir du panier, ou renvoie null si un produit n'a plus assez de stock
    private function createOrder(array $items, array $data, EntityManagerInterface $em, StockManager $stock): ?Order
    {
        // on verrouille les variantes pour que deux commandes simultanées
        // ne puissent pas vendre le meme dernier pot
        foreach ($items as $item) {
            $stock->lock($item['variant']);
            if ($item['variant']->getStock() < $item['quantity']) {
                return null;
            }
        }

        $order = new Order();
        $order->setUser($this->getUser());
        $order->setGuestEmail($this->getUser() ? null : $data['email']);
        $order->setStatus('pending');
        $order->setCreatedAt(new \DateTimeImmutable());
        $order->setDeliveryFirstName($data['firstName']);
        $order->setDeliveryLastName($data['lastName']);
        $order->setDeliveryStreet($data['street']);
        $order->setDeliveryCity($data['city']);
        $order->setDeliveryPostalCode($data['postalCode']);
        $order->setDeliveryCountry($data['country']);

        $total = 0.0;
        foreach ($items as $item) {
            $variant = $item['variant'];

            $orderItem = new OrderItem();
            $orderItem->setProductVariant($variant);
            $orderItem->setQuantity($item['quantity']);
            $orderItem->setUnitPrice($variant->getPrice());
            $orderItem->setWeight($variant->getWeight());
            $order->addOrderItem($orderItem);
            $em->persist($orderItem);

            $total += (float) $variant->getPrice() * $item['quantity'];

            $stock->move($variant, -$item['quantity'], 'sale', $this->getUser(), $order);
        }

        $order->setTotalPrice(number_format($total, 2, '.', ''));
        $em->persist($order);

        return $order;
    }

    #[Route('/confirmation/{id}', name: 'confirm', methods: ['GET'])]
    public function confirm(Order $order, Request $request): Response
    {
        $this->denyUnlessCanView($order, $request);

        return $this->render('checkout/confirm.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/confirmation/{id}/facture', name: 'invoice', methods: ['GET'])]
    public function invoice(Order $order, Request $request, InvoiceGenerator $invoices): Response
    {
        $this->denyUnlessCanView($order, $request);

        return new Response(
            $invoices->generate($order),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $invoices->filename($order)),
            ],
        );
    }

    // client connecté : sa propre commande ; invité : une commande passée dans cette session
    private function denyUnlessCanView(Order $order, Request $request): void
    {
        $allowed = $order->isGuest()
            ? in_array($order->getId(), $request->getSession()->get(self::GUEST_ORDERS_KEY, []), true)
            : $order->getUser() === $this->getUser();

        if (!$allowed) {
            throw $this->createAccessDeniedException();
        }
    }
}
