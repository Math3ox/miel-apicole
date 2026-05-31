<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ForgotPasswordController extends AbstractController
{
    #[Route('/mot-de-passe-oublie', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function request(
        Request $request,
        UserRepository $userRepo,
        EntityManagerInterface $em,
        MailerService $mailer,
    ): Response {
        if ($request->isMethod('POST')) {
            $email = trim($request->request->get('email', ''));
            $user = $email !== '' ? $userRepo->findOneBy(['email' => $email]) : null;

            if ($user && $user->isActive()) {
                // on genere un token aleatoire, on stocke sa version hashée et on envoie le lien par mail
                $token = bin2hex(random_bytes(32));
                $user->setResetToken(hash('sha256', $token));
                $user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
                $em->flush();

                try {
                    $mailer->sendPasswordReset($user, $token);
                } catch (\Throwable) {
                }
            }

            $this->addFlash('info', 'Si un compte est associé à cette adresse, un email de réinitialisation vient d\'être envoyé.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/forgot_password.html.twig');
    }

    #[Route('/reinitialiser-mot-de-passe/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function reset(
        string $token,
        Request $request,
        UserRepository $userRepo,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
    ): Response {
        $user = $userRepo->findOneBy(['resetToken' => hash('sha256', $token)]);

        if (!$user || $user->getResetTokenExpiresAt() === null || $user->getResetTokenExpiresAt() < new \DateTimeImmutable()) {
            $this->addFlash('error', 'Ce lien de réinitialisation est invalide ou a expiré.');
            return $this->redirectToRoute('app_forgot_password');
        }

        $errors = [];

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password', '');
            $confirm  = $request->request->get('passwordConfirm', '');

            if (strlen($password) < 8) {
                $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
            }
            if ($password !== $confirm) {
                $errors[] = 'Les mots de passe ne correspondent pas.';
            }

            if (empty($errors)) {
                $user->setPassword($hasher->hashPassword($user, $password));
                $user->setResetToken(null);
                $user->setResetTokenExpiresAt(null);
                $em->flush();

                $this->addFlash('success', 'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.');
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/reset_password.html.twig', [
            'token'  => $token,
            'errors' => $errors,
        ]);
    }
}
