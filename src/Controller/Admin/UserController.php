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
    // '' = simple client (ROLE_USER est ajouté automatiquement par User::getRoles)
    public const ROLES = [
        ''                => 'Client',
        'ROLE_APICULTEUR' => 'Apiculteur',
        'ROLE_ADMIN'      => 'Administrateur',
    ];

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
            $role      = $request->request->get('role', '');
            $isActive  = $request->request->has('isActive');

            if (!array_key_exists($role, self::ROLES)) {
                $errors[] = 'Rôle invalide.';
            }

            if ($firstName === '') {
                $errors[] = 'Le prénom est requis.';
            }
            if ($lastName === '') {
                $errors[] = 'Le nom est requis.';
            }

            $isSelf = $user === $this->getUser();
            if ($isSelf && ($role !== 'ROLE_ADMIN' || !$isActive)) {
                $errors[] = 'Vous ne pouvez pas retirer votre propre rôle admin ni désactiver votre compte.';
            }

            if (empty($errors)) {
                $user->setFirstName($firstName);
                $user->setLastName($lastName);
                $user->setRoles($role !== '' ? [$role] : []);
                $user->setIsActive($isActive);
                $em->flush();

                $this->addFlash('success', 'Utilisateur mis à jour.');
                return $this->redirectToRoute('admin_user_index');
            }
        }

        $currentRole = '';
        foreach (['ROLE_ADMIN', 'ROLE_APICULTEUR'] as $r) {
            if (in_array($r, $user->getRoles(), true)) {
                $currentRole = $r;
                break;
            }
        }

        return $this->render('admin/user/edit.html.twig', [
            'user'        => $user,
            'errors'      => $errors,
            'roles'       => self::ROLES,
            'currentRole' => $currentRole,
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
