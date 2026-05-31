<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Review;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ReviewController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('/produit/{id}/avis', name: 'app_review_add', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function add(
        Product $product,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $redirect = $this->redirectToRoute('app_shop_show', ['slug' => $product->getSlug()]);

        if (!$this->isCsrfTokenValid('review_add_' . $product->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $redirect;
        }

        $rating  = (int) $request->request->get('rating', 0);
        $comment = trim($request->request->get('comment', ''));

        if ($rating < 1 || $rating > 5) {
            $this->addFlash('error', 'Veuillez sélectionner une note entre 1 et 5 étoiles.');
            return $redirect;
        }

        $review = new Review();
        $review->setProduct($product);
        $review->setUser($this->getUser());
        $review->setRating($rating);
        $review->setComment($comment !== '' ? $comment : null);
        $review->setIsApprouved(false);
        $review->setCreatedAt(new \DateTimeImmutable());

        $em->persist($review);
        $em->flush();

        $this->addFlash('success', 'Merci ! Votre avis a été soumis et sera publié après modération.');
        return $redirect;
    }
}
