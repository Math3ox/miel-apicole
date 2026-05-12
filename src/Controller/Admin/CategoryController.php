<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/admin/categories', name: 'admin_category_')]
class CategoryController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(CategoryRepository $categoryRepo): Response
    {
        return $this->render('admin/category/index.html.twig', [
            'categories' => $categoryRepo->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/nouvelle', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $errors = [];
        $data   = [];

        if ($request->isMethod('POST')) {
            $data   = $this->extractData($request);
            $errors = $this->validate($data, $em);

            if (empty($errors)) {
                $category = new Category();
                $this->hydrate($category, $data, $em);
                $em->persist($category);
                $em->flush();

                $this->addFlash('success', 'Catégorie créée avec succès.');
                return $this->redirectToRoute('admin_category_index');
            }
        }

        return $this->render('admin/category/form.html.twig', [
            'category' => null,
            'errors'   => $errors,
            'data'     => $data,
        ]);
    }

    #[Route('/{id}/editer', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        CategoryRepository $categoryRepo,
    ): Response {
        $category = $categoryRepo->find($id);
        if (!$category) {
            throw $this->createNotFoundException('Catégorie introuvable.');
        }

        $errors = [];
        $data   = [];

        if ($request->isMethod('POST')) {
            $data   = $this->extractData($request);
            $errors = $this->validate($data, $em, $category->getId());

            if (empty($errors)) {
                $this->hydrate($category, $data, $em, $category->getId());
                $em->flush();

                $this->addFlash('success', 'Catégorie modifiée avec succès.');
                return $this->redirectToRoute('admin_category_index');
            }
        }

        return $this->render('admin/category/form.html.twig', [
            'category' => $category,
            'errors'   => $errors,
            'data'     => $data,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'delete', methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        CategoryRepository $categoryRepo,
    ): Response {
        $category = $categoryRepo->find($id);
        if (!$category) {
            throw $this->createNotFoundException('Catégorie introuvable.');
        }

        if (!$this->isCsrfTokenValid('delete_category_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('admin_category_index');
        }

        if ($category->getProducts()->count() > 0) {
            $this->addFlash('error', 'Impossible de supprimer une catégorie qui contient des produits.');
            return $this->redirectToRoute('admin_category_index');
        }

        $em->remove($category);
        $em->flush();

        $this->addFlash('success', 'Catégorie supprimée.');
        return $this->redirectToRoute('admin_category_index');
    }

    private function extractData(Request $request): array
    {
        return [
            'name'        => trim($request->request->get('name', '')),
            'slug'        => trim($request->request->get('slug', '')),
            'description' => trim($request->request->get('description', '')),
        ];
    }

    private function validate(array $data, EntityManagerInterface $em, ?int $excludeId = null): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Le nom est requis.';
            return $errors;
        }

        $slugger  = new AsciiSlugger('fr');
        $baseSlug = $slugger->slug($data['slug'] !== '' ? $data['slug'] : $data['name'])->lower()->toString();
        $existing = $em->getRepository(Category::class)->findOneBy(['slug' => $baseSlug]);

        if ($existing && $existing->getId() !== $excludeId) {
            $errors[] = 'Une catégorie avec ce nom (ou ce slug) existe déjà.';
        }

        return $errors;
    }

    private function hydrate(Category $category, array $data, EntityManagerInterface $em, ?int $excludeId = null): void
    {
        $slugger  = new AsciiSlugger('fr');
        $baseSlug = $slugger->slug($data['slug'] !== '' ? $data['slug'] : $data['name'])->lower()->toString();
        $slug     = $this->uniqueSlug($baseSlug, $em, $excludeId);

        $category->setName($data['name']);
        $category->setSlug($slug);
        $category->setDescription($data['description'] !== '' ? $data['description'] : null);
    }

    private function uniqueSlug(string $base, EntityManagerInterface $em, ?int $excludeId = null): string
    {
        $repo = $em->getRepository(Category::class);
        $slug = $base;
        $i    = 1;

        while (true) {
            $existing = $repo->findOneBy(['slug' => $slug]);
            if (!$existing || ($excludeId !== null && $existing->getId() === $excludeId)) {
                break;
            }
            $slug = $base . '-' . (++$i);
        }

        return $slug;
    }
}
