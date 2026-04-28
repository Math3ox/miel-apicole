<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/inscription', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $errors = [];
        $data   = [];

        if ($request->isMethod('POST')) {
            $data = [
                'firstName'       => trim($request->request->get('firstName', '')),
                'lastName'        => trim($request->request->get('lastName', '')),
                'email'           => trim($request->request->get('email', '')),
                'password'        => $request->request->get('password', ''),
                'passwordConfirm' => $request->request->get('passwordConfirm', ''),
            ];

            if (empty($data['firstName'])) {
                $errors[] = 'Le prénom est requis.';
            }
            if (empty($data['lastName'])) {
                $errors[] = 'Le nom est requis.';
            }
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Adresse email invalide.';
            }
            if (strlen($data['password']) < 8) {
                $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
            }
            if ($data['password'] !== $data['passwordConfirm']) {
                $errors[] = 'Les mots de passe ne correspondent pas.';
            }

            if (empty($errors) && $em->getRepository(User::class)->findOneBy(['email' => $data['email']])) {
                $errors[] = 'Cette adresse email est déjà utilisée.';
            }

            if (empty($errors)) {
                $user = new User();
                $user->setFirstName($data['firstName']);
                $user->setLastName($data['lastName']);
                $user->setEmail($data['email']);
                $user->setPassword($hasher->hashPassword($user, $data['password']));
                $user->setRoles([]);
                $user->setCreatedAt(new \DateTimeImmutable());

                $em->persist($user);
                $em->flush();

                $this->addFlash('success', 'Compte créé avec succès ! Vous pouvez vous connecter.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('registration/register.html.twig', [
            'errors' => $errors,
            'data'   => $data,
        ]);
    }
}
