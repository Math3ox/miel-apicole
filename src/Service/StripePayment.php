<?php

namespace App\Service;

use App\Entity\Order;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\StripeClient;
use Stripe\Webhook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// paiement des commandes via Stripe Checkout (page de paiement hébergée par Stripe :
// aucune donnée de carte ne passe par notre serveur)
class StripePayment
{
    // durée laissée au client pour payer (minimum imposé par Stripe : 30 min)
    private const SESSION_LIFETIME = 30 * 60;

    private ?StripeClient $client = null;

    public function __construct(
        #[Autowire('%env(STRIPE_SECRET_KEY)%')] private readonly string $secretKey,
        #[Autowire('%env(STRIPE_WEBHOOK_SECRET)%')] private readonly string $webhookSecret,
        private readonly EntityManagerInterface $em,
        private readonly StockManager $stock,
        private readonly MailerService $mailer,
        private readonly UrlGeneratorInterface $urls,
        private readonly LoggerInterface $logger,
    ) {}

    public function isConfigured(): bool
    {
        return $this->secretKey !== '';
    }

    // crée la page de paiement Stripe pour la commande et renvoie son adresse
    public function createCheckout(Order $order): string
    {
        $lineItems = [];
        foreach ($order->getOrderItems() as $item) {
            $variant = $item->getProductVariant();
            $lineItems[] = [
                'quantity'   => $item->getQuantity(),
                'price_data' => [
                    'currency'     => 'eur',
                    'unit_amount'  => (int) round((float) $item->getUnitPrice() * 100),
                    'product_data' => [
                        'name' => sprintf('%s — %d g', $variant->getProduct()->getName(), $item->getWeight()),
                    ],
                ],
            ];
        }

        $session = $this->client()->checkout->sessions->create([
            'mode'                => 'payment',
            'line_items'          => $lineItems,
            'customer_email'      => $order->getCustomerEmail(),
            'client_reference_id' => (string) $order->getId(),
            'metadata'            => ['order_id' => (string) $order->getId()],
            'locale'              => 'fr',
            'expires_at'          => time() + self::SESSION_LIFETIME,
            // {CHECKOUT_SESSION_ID} est remplacé par Stripe au retour
            'success_url'         => $this->urls->generate('app_checkout_confirm', ['id' => $order->getId()], UrlGeneratorInterface::ABSOLUTE_URL) . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'          => $this->urls->generate('app_checkout_cancel', ['id' => $order->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
        ], [
            // évite de créer deux sessions si la requête est rejouée
            'idempotency_key' => 'order-' . $order->getId(),
        ]);

        $order->setStripeSessionId($session->id);
        $this->em->flush();

        return $session->url;
    }

    // relit l'état du paiement chez Stripe (retour du client, tâche de nettoyage)
    public function sync(Order $order): void
    {
        if (!$order->getStripeSessionId() || $order->getStatus() !== 'pending') {
            return;
        }

        $session = $this->client()->checkout->sessions->retrieve($order->getStripeSessionId());
        $this->applySession($order, $session);
    }

    // le client a cliqué « retour » sur la page Stripe : on ferme la session
    // pour qu'il ne puisse plus payer, puis on annule la commande
    public function abandon(Order $order): void
    {
        if ($order->getStatus() !== 'pending') {
            return;
        }

        if ($order->getStripeSessionId()) {
            $session = $this->client()->checkout->sessions->retrieve($order->getStripeSessionId());
            if ($session->status === Session::STATUS_OPEN) {
                $session = $this->client()->checkout->sessions->expire($session->id);
            }
            // payée entre-temps (rare) : on la garde
            if ($this->applySession($order, $session)) {
                return;
            }
        }

        $this->cancelUnpaid($order, 'Paiement abandonné par le client');
    }

    // webhook : Stripe nous prévient d'un paiement réussi ou d'une session expirée
    public function handleWebhook(string $payload, string $signature): void
    {
        // lève une exception si la signature est fausse (requête qui ne vient pas de Stripe)
        $event = Webhook::constructEvent($payload, $signature, $this->webhookSecret);

        if (!in_array($event->type, [Event::CHECKOUT_SESSION_COMPLETED, Event::CHECKOUT_SESSION_ASYNC_PAYMENT_SUCCEEDED, Event::CHECKOUT_SESSION_EXPIRED], true)) {
            return;
        }

        /** @var Session $session */
        $session = $event->data->object;
        $order = $this->em->getRepository(Order::class)->findOneBy(['stripeSessionId' => $session->id]);

        if ($order === null) {
            $this->logger->warning('Webhook Stripe pour une session inconnue', ['session' => $session->id]);
            return;
        }

        $this->applySession($order, $session);
    }

    // applique l'état d'une session Stripe à la commande ; renvoie true si elle est payée
    private function applySession(Order $order, Session $session): bool
    {
        if ($session->payment_status === Session::PAYMENT_STATUS_PAID) {
            $this->markPaid($order);
            return true;
        }

        if ($session->status === Session::STATUS_EXPIRED) {
            $this->cancelUnpaid($order, 'Délai de paiement dépassé');
        }

        return false;
    }

    private function markPaid(Order $order): void
    {
        // verrou : le retour du client et le webhook arrivent souvent en même temps,
        // un seul des deux doit passer la commande en payée et envoyer les mails
        $changed = $this->em->wrapInTransaction(function () use ($order): bool {
            $this->em->refresh($order, LockMode::PESSIMISTIC_WRITE);
            if ($order->isPaid() || $order->getStatus() !== 'pending') {
                return false;
            }

            $order->setStatus('paid');
            $order->setPaidAt(new \DateTimeImmutable());
            $order->setUpdatedAt(new \DateTimeImmutable());

            return true;
        });

        if ($changed) {
            try {
                $this->mailer->sendOrderConfirmation($order);
                $this->mailer->sendAdminOrderNotification($order);
            } catch (\Throwable $e) {
                $this->logger->error('Mails de confirmation non envoyés', ['order' => $order->getId(), 'error' => $e->getMessage()]);
            }
        }
    }

    // commande jamais payée : annulée et ses pots remis en stock
    private function cancelUnpaid(Order $order, string $reason): void
    {
        $this->em->wrapInTransaction(function () use ($order, $reason): void {
            $this->em->refresh($order, LockMode::PESSIMISTIC_WRITE);
            if ($order->getStatus() !== 'pending') {
                return;
            }

            foreach ($order->getOrderItems() as $item) {
                $this->stock->lock($item->getProductVariant());
                $this->stock->move($item->getProductVariant(), $item->getQuantity(), 'cancel', null, $order, $reason);
            }

            $order->setStatus('cancelled');
            $order->setUpdatedAt(new \DateTimeImmutable());
        });
    }

    private function client(): StripeClient
    {
        if (!$this->isConfigured()) {
            throw new \LogicException('STRIPE_SECRET_KEY n\'est pas configurée.');
        }

        return $this->client ??= new StripeClient($this->secretKey);
    }
}
