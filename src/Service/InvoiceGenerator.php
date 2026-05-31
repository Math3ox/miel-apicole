<?php

namespace App\Service;

use App\Entity\Order;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class InvoiceGenerator
{
    public function __construct(
        private readonly Environment $twig,
    ) {}

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

    public function filename(Order $order): string
    {
        return sprintf('facture-%05d.pdf', $order->getId());
    }
}
