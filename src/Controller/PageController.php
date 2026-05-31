<?php

namespace App\Controller;

use App\Service\MailerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PageController extends AbstractController
{
    #[Route('/a-propos', name: 'app_about', methods: ['GET'])]
    public function about(): Response
    {
        return $this->render('pages/about.html.twig');
    }

    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function contact(Request $request, MailerService $mailer): Response
    {
        $errors = [];
        $data = [
            'name'    => $this->getUser() ? trim($this->getUser()->getFirstName() . ' ' . $this->getUser()->getLastName()) : '',
            'email'   => $this->getUser()?->getEmail() ?? '',
            'subject' => '',
            'message' => '',
        ];

        if ($request->isMethod('POST')) {
            $data = [
                'name'    => trim($request->request->get('name', '')),
                'email'   => trim($request->request->get('email', '')),
                'subject' => trim($request->request->get('subject', '')),
                'message' => trim($request->request->get('message', '')),
            ];

            if (!$this->isCsrfTokenValid('contact', $request->request->get('_token'))) {
                $errors[] = 'Token de sécurité invalide.';
            }
            if ($data['name'] === '') {
                $errors[] = 'Le nom est requis.';
            }
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Adresse email invalide.';
            }
            if ($data['message'] === '') {
                $errors[] = 'Le message ne peut pas être vide.';
            }

            if (empty($errors)) {
                try {
                    $mailer->sendContactMessage($data['name'], $data['email'], $data['subject'], $data['message']);
                    $this->addFlash('success', 'Votre message a bien été envoyé. Nous vous répondrons rapidement.');
                    return $this->redirectToRoute('app_contact');
                } catch (\Throwable) {
                    $errors[] = "L'envoi a échoué, veuillez réessayer plus tard.";
                }
            }
        }

        return $this->render('pages/contact.html.twig', [
            'data'   => $data,
            'errors' => $errors,
        ]);
    }
}
