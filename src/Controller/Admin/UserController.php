<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/utilisateurs', name: 'admin_user_')]
class UserController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(UserRepository $userRepo): Response
    {
        return $this->render('admin/user/index.html.twig', [
            'users' => $userRepo->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/{id}/editer', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        User $user,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_user_' . $user->getId(), $request->request->get('_token'))) {
                $this->addFlash('error', 'Token de sécurité invalide.');
                return $this->redirectToRoute('admin_user_index');
            }

            $firstName = trim($request->request->get('firstName', ''));
            $lastName  = trim($request->request->get('lastName', ''));
            $isAdmin   = $request->request->has('isAdmin');
            $isActive  = $request->request->has('isActive');

            if ($firstName === '') {
                $errors[] = 'Le prénom est requis.';
            }
            if ($lastName === '') {
                $errors[] = 'Le nom est requis.';
            }

            $isSelf = $user === $this->getUser();
            if ($isSelf && (!$isAdmin || !$isActive)) {
                $errors[] = 'Vous ne pouvez pas retirer votre propre rôle admin ni désactiver votre compte.';
            }

            if (empty($errors)) {
                $user->setFirstName($firstName);
                $user->setLastName($lastName);
                $user->setRoles($isAdmin ? ['ROLE_ADMIN'] : []);
                $user->setIsActive($isActive);
                $em->flush();

                $this->addFlash('success', 'Utilisateur mis à jour.');
                return $this->redirectToRoute('admin_user_index');
            }
        }

        return $this->render('admin/user/edit.html.twig', [
            'user'   => $user,
            'errors' => $errors,
        ]);
    }

    #[Route('/{id}/desactiver', name: 'toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleActive(
        User $user,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('toggle_user_' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('admin_user_index');
        }

        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas désactiver votre propre compte.');
            return $this->redirectToRoute('admin_user_index');
        }

        $user->setIsActive(!$user->isActive());
        $em->flush();

        $this->addFlash('success', $user->isActive() ? 'Compte réactivé.' : 'Compte désactivé.');
        return $this->redirectToRoute('admin_user_index');
    }
}
