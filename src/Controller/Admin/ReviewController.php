<?php

namespace App\Controller\Admin;

use App\Entity\Review;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/avis', name: 'admin_review_')]
class ReviewController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(ReviewRepository $reviewRepo): Response
    {
        $reviews = $reviewRepo->findBy([], ['createdAt' => 'DESC']);

        return $this->render('admin/review/index.html.twig', [
            'reviews'      => $reviews,
            'pendingCount' => count(array_filter($reviews, fn (Review $r) => !$r->isApprouved())),
        ]);
    }

    #[Route('/{id}/approuver', name: 'approve', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function approve(
        Review $review,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('approve_review_' . $review->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('admin_review_index');
        }

        $review->setIsApprouved(true);
        $em->flush();

        $this->addFlash('success', 'Avis approuvé et publié.');
        return $this->redirectToRoute('admin_review_index');
    }

    #[Route('/{id}/supprimer', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(
        Review $review,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('delete_review_' . $review->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('admin_review_index');
        }

        $em->remove($review);
        $em->flush();

        $this->addFlash('success', 'Avis supprimé.');
        return $this->redirectToRoute('admin_review_index');
    }
}
