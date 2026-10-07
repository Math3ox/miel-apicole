<?php

namespace App\Command;

use App\Entity\Order;
use App\Service\StripePayment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// filet de sécurité si un webhook Stripe n'arrive pas (serveur injoignable, site en local...) :
// les commandes en attente depuis plus de 35 min sont vérifiées chez Stripe,
// puis passées en payées ou annulées (pots remis en stock). A lancer en cron toutes les 15 min.
#[AsCommand(name: 'app:payments:sync', description: 'Vérifie chez Stripe les commandes en attente de paiement depuis plus de 35 min')]
class SyncPaymentsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly StripePayment $payment,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $orders = $this->em->getRepository(Order::class)->createQueryBuilder('o')
            ->andWhere('o.status = :pending')->setParameter('pending', 'pending')
            ->andWhere('o.createdAt < :limit')->setParameter('limit', new \DateTimeImmutable('-35 minutes'))
            ->getQuery()
            ->getResult();

        foreach ($orders as $order) {
            try {
                // sans session Stripe (création échouée) ou session expirée : annulée ; payée : validée
                $order->getStripeSessionId() ? $this->payment->sync($order) : $this->payment->abandon($order);
                $io->writeln(sprintf('Commande #%d : %s', $order->getId(), $order->getStatus()));
            } catch (\Throwable $e) {
                $io->warning(sprintf('Commande #%d : %s', $order->getId(), $e->getMessage()));
            }
        }

        $io->success(sprintf('%d commande(s) vérifiée(s).', count($orders)));

        return Command::SUCCESS;
    }
}
