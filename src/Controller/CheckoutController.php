<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Service\CartService;
use App\Service\MailerService;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/commande', name: 'app_checkout_')]
class CheckoutController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        CartService $cart,
        EntityManagerInterface $em,
        MailerService $mailer,
    ): Response {
        $items = $cart->getFullCart();

        if (empty($items)) {
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('app_cart_index');
        }

        $errors = [];
        $data = [
            'firstName'  => $this->getUser()->getFirstName(),
            'lastName'   => $this->getUser()->getLastName(),
            'street'     => '',
            'city'       => '',
            'postalCode' => '',
            'country'    => 'France',
        ];

        if ($request->isMethod('POST')) {
            $data = [
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

            foreach (['firstName', 'lastName', 'street', 'city', 'postalCode', 'country'] as $field) {
                if ($data[$field] === '') {
                    $errors[$field] = 'Ce champ est obligatoire.';
                }
            }

            if (empty($errors)) {
                $order = $em->wrapInTransaction(fn () => $this->createOrder($items, $data, $em));

                if ($order === null) {
                    // le stock a bougé depuis l'ajout au panier : on ajuste le panier et on prévient le client
                    foreach ($items as $item) {
                        $cart->update($item['variant']->getId(), $item['quantity']);
                    }
                    $this->addFlash('warning', 'Certains produits n\'ont plus assez de stock : votre panier a été ajusté, vérifiez-le avant de commander.');
                    return $this->redirectToRoute('app_cart_index');
                }

                $cart->clear();

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
    private function createOrder(array $items, array $data, EntityManagerInterface $em): ?Order
    {
        // on verrouille les variantes (SELECT ... FOR UPDATE) pour que deux commandes
        // simultanées ne puissent pas vendre le meme dernier pot
        foreach ($items as $item) {
            $em->refresh($item['variant'], LockMode::PESSIMISTIC_WRITE);
            if ($item['variant']->getStock() < $item['quantity']) {
                return null;
            }
        }

        $order = new Order();
        $order->setUser($this->getUser());
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

            $variant->setStock($variant->getStock() - $item['quantity']);
        }

        $order->setTotalPrice(number_format($total, 2, '.', ''));
        $em->persist($order);

        return $order;
    }

    #[Route('/confirmation/{id}', name: 'confirm', methods: ['GET'])]
    public function confirm(Order $order): Response
    {
        if ($order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('checkout/confirm.html.twig', [
            'order' => $order,
        ]);
    }
}
