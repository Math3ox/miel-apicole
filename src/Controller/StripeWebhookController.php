<?php

namespace App\Controller;

use App\Service\StripePayment;
use Psr\Log\LoggerInterface;
use Stripe\Exception\SignatureVerificationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// Stripe appelle cette adresse quand un paiement réussit ou qu'une session expire
// (même si le client a fermé son navigateur). A déclarer dans le tableau de bord Stripe.
class StripeWebhookController extends AbstractController
{
    #[Route('/paiement/stripe/webhook', name: 'app_stripe_webhook', methods: ['POST'])]
    public function __invoke(Request $request, StripePayment $payment, LoggerInterface $logger): Response
    {
        try {
            $payment->handleWebhook($request->getContent(), (string) $request->headers->get('Stripe-Signature'));
        } catch (SignatureVerificationException|\UnexpectedValueException $e) {
            $logger->warning('Webhook Stripe refusé', ['error' => $e->getMessage()]);
            return new Response('Signature invalide', Response::HTTP_BAD_REQUEST);
        }

        return new Response('ok');
    }
}
