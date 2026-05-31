<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class MailerService
{
    private const FROM_EMAIL  = 'contact@miel-apicole.fr';
    private const FROM_NAME   = 'Miel Apicole';
    private const ADMIN_EMAIL = 'contact@miel-apicole.fr';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly InvoiceGenerator $invoices,
    ) {}

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

    public function sendPasswordReset(User $user, string $token): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address(self::FROM_EMAIL, self::FROM_NAME))
            ->to(new Address($user->getEmail(), trim($user->getFirstName() . ' ' . $user->getLastName())))
            ->subject('Réinitialisation de votre mot de passe')
            ->htmlTemplate('emails/reset_password.html.twig')
            ->context([
                'user'  => $user,
                'token' => $token,
            ]);

        $this->mailer->send($email);
    }

    public function sendAdminOrderNotification(Order $order): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address(self::FROM_EMAIL, self::FROM_NAME))
            ->to(self::ADMIN_EMAIL)
            ->subject(sprintf('Nouvelle commande #%d', $order->getId()))
            ->htmlTemplate('emails/admin_order_notification.html.twig')
            ->context(['order' => $order]);

        $this->mailer->send($email);
    }

    public function sendContactMessage(string $name, string $fromEmail, string $subject, string $message): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address(self::FROM_EMAIL, self::FROM_NAME))
            ->to(self::ADMIN_EMAIL)
            ->replyTo(new Address($fromEmail, $name))
            ->subject('[Contact] ' . ($subject !== '' ? $subject : 'Nouveau message'))
            ->htmlTemplate('emails/contact.html.twig')
            ->context([
                'name'    => $name,
                'email'   => $fromEmail,
                'subject' => $subject,
                'message' => $message,
            ]);

        $this->mailer->send($email);
    }
}
