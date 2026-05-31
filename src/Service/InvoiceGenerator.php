<?php

namespace App\Service;

use App\Entity\Order;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * Génère la facture PDF d'une commande à partir d'un template Twig rendu en HTML,
 * converti en PDF via dompdf. Aucun outil auto-généré : tout est piloté manuellement.
 */
class InvoiceGenerator
{
    public function __construct(
        private readonly Environment $twig,
    ) {}

    /**
     * Retourne le contenu binaire du PDF de la facture.
     */
    public function generate(Order $order): string
    {
        $html = $this->twig->render('invoice/facture.html.twig', [
            'order' => $order,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Nom de fichier normalisé pour le téléchargement.
     */
    public function filename(Order $order): string
    {
        return sprintf('facture-%05d.pdf', $order->getId());
    }
}
