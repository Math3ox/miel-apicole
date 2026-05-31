<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Envoi manuel des e-mails transactionnels via le composant Mailer.
 * Aucun bundle auto-généré : on construit et envoie chaque message ici.
 */
class MailerService
{
    private const FROM_EMAIL = 'contact@miel-apicole.fr';
    private const FROM_NAME  = 'Miel Apicole';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly InvoiceGenerator $invoices,
    ) {}

    /**
     * E-mail de bienvenue après l'inscription.
     */
    public function sendWelcome(User $user): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address(self::FROM_EMAIL, self::FROM_NAME))
            ->to(new Address($user->getEmail(), trim($user->getFirstName() . ' ' . $user->getLastName())))
            ->subject('Bienvenue chez Miel Apicole 🍯')
            ->htmlTemplate('emails/welcome.html.twig')
            ->context(['user' => $user]);

        $this->mailer->send($email);
    }

    /**
     * Confirmation de commande, avec la facture PDF en pièce jointe.
     */
    public function sendOrderConfirmation(Order $order): void
    {
        $user = $order->getUser();

        $email = (new TemplatedEmail())
            ->from(new Address(self::FROM_EMAIL, self::FROM_NAME))
            ->to(new Address($user->getEmail(), trim($user->getFirstName() . ' ' . $user->getLastName())))
            ->subject(sprintf('Confirmation de votre commande #%d', $order->getId()))
            ->htmlTemplate('emails/order_confirmation.html.twig')
            ->context(['order' => $order]);

        $email->attach(
            $this->invoices->generate($order),
            $this->invoices->filename($order),
            'application/pdf',
        );

        $this->mailer->send($email);
    }
}
