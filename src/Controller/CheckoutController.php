<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Service\CartService;
use App\Service\MailerService;
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

            foreach (['firstName', 'lastName', 'street', 'city', 'postalCode', 'country'] as $field) {
                if ($data[$field] === '') {
                    $errors[$field] = 'Ce champ est obligatoire.';
                }
            }

            if (empty($errors)) {
                // on cree la commande a partir du panier
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
                    $orderItem = new OrderItem();
                    $orderItem->setProductVariant($item['variant']);
                    $orderItem->setQuantity($item['quantity']);
                    $orderItem->setUnitPrice($item['variant']->getPrice());
                    $orderItem->setWeight($item['variant']->getWeight());
                    $order->addOrderItem($orderItem);
                    $em->persist($orderItem);

                    $total += $item['subtotal'];

                    $variant = $item['variant'];
                    $variant->setStock(max(0, $variant->getStock() - $item['quantity']));
                    $em->persist($variant);
                }

                $order->setTotalPrice(number_format($total, 2, '.', ''));
                $em->persist($order);
                $em->flush();

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
