<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

// mot de passe oublié via symfonycasts/reset-password-bundle (génération, stockage et expiration des liens)
class ForgotPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
    ) {}

    #[Route('/mot-de-passe-oublie', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function request(
        Request $request,
        UserRepository $userRepo,
        MailerService $mailer,
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('forgot_password', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token de sécurité invalide, veuillez réessayer.');
                return $this->redirectToRoute('app_forgot_password');
            }

            $email = trim($request->request->get('email', ''));
            $user = $email !== '' ? $userRepo->findOneBy(['email' => $email]) : null;

            if ($user && $user->isActive()) {
                try {
                    $resetToken = $this->resetPasswordHelper->generateResetToken($user);
                    $mailer->sendPasswordReset($user, $resetToken->getToken());
                } catch (ResetPasswordExceptionInterface) {
                    // demande trop rapprochée : on ne dit rien, pour ne pas révéler si le compte existe
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
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        ?string $token = null,
    ): Response {
        // le token du mail est mis en session puis retiré de l'URL,
        // pour qu'il ne fuite pas (historique, en-tête Referer...)
        if ($token) {
            $this->storeTokenInSession($token);
            return $this->redirectToRoute('app_reset_password');
        }

        $token = $this->getTokenFromSession();

        try {
            /** @var User $user */
            $user = $token ? $this->resetPasswordHelper->validateTokenAndFetchUser($token) : null;
        } catch (ResetPasswordExceptionInterface) {
            $user = null;
        }

        if (!$user) {
            $this->addFlash('error', 'Ce lien de réinitialisation est invalide ou a expiré.');
            return $this->redirectToRoute('app_forgot_password');
        }

        $errors = [];

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password', '');
            $confirm  = $request->request->get('passwordConfirm', '');

            if (!$this->isCsrfTokenValid('reset_password', $request->request->get('_token'))) {
                $errors[] = 'Token de sécurité invalide, veuillez réessayer.';
            }
            if (strlen($password) < 8) {
                $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
            }
            if ($password !== $confirm) {
                $errors[] = 'Les mots de passe ne correspondent pas.';
            }

            if (empty($errors)) {
                // le lien ne sert qu'une fois
                $this->resetPasswordHelper->removeResetRequest($token);

                $user->setPassword($hasher->hashPassword($user, $password));
                $em->flush();

                $this->cleanSessionAfterReset();

                $this->addFlash('success', 'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.');
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/reset_password.html.twig', [
            'errors' => $errors,
        ]);
    }
}
