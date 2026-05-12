<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/admin/produits', name: 'admin_product_')]
class ProductController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(ProductRepository $productRepo): Response
    {
        return $this->render('admin/product/index.html.twig', [
            'products' => $productRepo->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/nouveau', name: 'create', methods: ['GET', 'POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        CategoryRepository $categoryRepo,
    ): Response {
        $errors = [];
        $data   = [];

        if ($request->isMethod('POST')) {
            [$errors, $data] = $this->extractAndValidate($request);

            if (empty($errors)) {
                $product = new Product();
                $product->setCreatedAt(new \DateTimeImmutable());
                $this->hydrateProduct($product, $data, $request, $em);

                $em->persist($product);
                $em->flush();

                $this->addFlash('success', 'Produit créé avec succès.');
                return $this->redirectToRoute('admin_product_index');
            }
        }

        return $this->render('admin/product/form.html.twig', [
            'product'    => null,
            'categories' => $categoryRepo->findBy([], ['name' => 'ASC']),
            'errors'     => $errors,
            'data'       => $data,
        ]);
    }

    #[Route('/{id}/editer', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        CategoryRepository $categoryRepo,
        ProductRepository $productRepo,
    ): Response {
        $product = $productRepo->find($id);
        if (!$product) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        $errors = [];
        $data   = [];

        if ($request->isMethod('POST')) {
            [$errors, $data] = $this->extractAndValidate($request);

            if (empty($errors)) {
                $this->hydrateProduct($product, $data, $request, $em, true);
                $this->syncExistingVariants($product, $request, $em);

                $em->flush();

                $this->addFlash('success', 'Produit modifié avec succès.');
                return $this->redirectToRoute('admin_product_index');
            }
        }

        return $this->render('admin/product/form.html.twig', [
            'product'    => $product,
            'categories' => $categoryRepo->findBy([], ['name' => 'ASC']),
            'errors'     => $errors,
            'data'       => $data,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'delete', methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        ProductRepository $productRepo,
    ): Response {
        $product = $productRepo->find($id);
        if (!$product) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        if (!$this->isCsrfTokenValid('delete_product_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('admin_product_index');
        }

        if ($product->getImage()) {
            $path = $this->getParameter('kernel.project_dir') . '/public/uploads/products/' . $product->getImage();
            if (file_exists($path)) {
                unlink($path);
            }
        }

        $em->remove($product);
        $em->flush();

        $this->addFlash('success', 'Produit supprimé.');
        return $this->redirectToRoute('admin_product_index');
    }

    #[Route('/{id}/variante/{variantId}/supprimer', name: 'delete_variant', methods: ['POST'])]
    public function deleteVariant(
        int $id,
        int $variantId,
        Request $request,
        EntityManagerInterface $em,
        ProductRepository $productRepo,
    ): Response {
        $product = $productRepo->find($id);
        if (!$product) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        if (!$this->isCsrfTokenValid('delete_variant_' . $variantId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('admin_product_edit', ['id' => $id]);
        }

        $variant = $em->find(ProductVariant::class, $variantId);
        if ($variant && $variant->getProduct() === $product) {
            $product->removeProductVariant($variant);
            $em->remove($variant);
            $em->flush();
            $this->addFlash('success', 'Variante supprimée.');
        }

        return $this->redirectToRoute('admin_product_edit', ['id' => $id]);
    }

    private function extractAndValidate(Request $request): array
    {
        $data = [
            'name'         => trim($request->request->get('name', '')),
            'slug'         => trim($request->request->get('slug', '')),
            'description'  => trim($request->request->get('description', '')),
            'price'        => trim($request->request->get('price', '')),
            'tastingAdvice'=> trim($request->request->get('tastingAdvice', '')),
            'allergens'    => trim($request->request->get('allergens', '')),
            'categoryId'   => $request->request->get('category', ''),
            'isBestSeller' => $request->request->has('isBestSeller'),
            'isOnSale'     => $request->request->has('isOnSale'),
        ];

        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Le nom du produit est requis.';
        }
        if ($data['categoryId'] === '') {
            $errors[] = 'La catégorie est requise.';
        }
        if (!is_numeric($data['price']) || (float) $data['price'] < 0) {
            $errors[] = 'Le prix doit être un nombre positif.';
        }

        return [$errors, $data];
    }

    private function hydrateProduct(
        Product $product,
        array $data,
        Request $request,
        EntityManagerInterface $em,
        bool $isEdit = false,
    ): void {
        $slugger  = new AsciiSlugger('fr');
        $baseSlug = $slugger->slug($data['slug'] !== '' ? $data['slug'] : $data['name'])->lower()->toString();
        $slug     = $this->uniqueSlug($baseSlug, $em, $isEdit ? $product->getId() : null);

        $category = $em->find(Category::class, (int) $data['categoryId']);

        $product->setName($data['name']);
        $product->setSlug($slug);
        $product->setDesciption($data['description'] !== '' ? $data['description'] : null);
        $product->setPrice($data['price']);
        $product->setTastingAdvice($data['tastingAdvice'] !== '' ? $data['tastingAdvice'] : null);
        $product->setAllergens($data['allergens'] !== '' ? $data['allergens'] : null);
        $product->setIsBestSeller($data['isBestSeller']);
        $product->setIsOnSale($data['isOnSale']);
        $product->setCategory($category);

        $imageFile = $request->files->get('image');
        if ($imageFile) {
            $ext = strtolower($imageFile->getClientOriginalExtension());
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $filename  = uniqid('product_') . '.' . $ext;
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/products/';

                if ($isEdit && $product->getImage()) {
                    $old = $uploadDir . $product->getImage();
                    if (file_exists($old)) {
                        unlink($old);
                    }
                }

                $imageFile->move($uploadDir, $filename);
                $product->setImage($filename);
            }
        }

        // New variants (new_weight[], new_price[], new_stock[])
        $allPost = $request->request->all();
        $weights = $allPost['new_weight'] ?? [];
        $prices  = $allPost['new_price']  ?? [];
        $stocks  = $allPost['new_stock']  ?? [];

        foreach ($weights as $i => $weight) {
            $w = (int) $weight;
            $p = $prices[$i] ?? null;
            $s = $stocks[$i] ?? null;

            if ($w > 0 && is_numeric($p) && is_numeric($s)) {
                $variant = new ProductVariant();
                $variant->setWeight($w);
                $variant->setPrice((string) (float) $p);
                $variant->setStock((int) $s);
                $product->addProductVariant($variant);
                $em->persist($variant);
            }
        }
    }

    private function syncExistingVariants(Product $product, Request $request, EntityManagerInterface $em): void
    {
        $allPost         = $request->request->all();
        $existingWeights = $allPost['existing_weight'] ?? [];
        $existingPrices  = $allPost['existing_price']  ?? [];
        $existingStocks  = $allPost['existing_stock']  ?? [];

        foreach ($product->getProductVariants() as $variant) {
            $vid = (string) $variant->getId();

            if (!isset($existingWeights[$vid])) {
                continue;
            }

            $w = (int) $existingWeights[$vid];
            $p = $existingPrices[$vid] ?? null;
            $s = $existingStocks[$vid] ?? null;

            if ($w > 0 && is_numeric($p) && is_numeric($s)) {
                $variant->setWeight($w);
                $variant->setPrice((string) (float) $p);
                $variant->setStock((int) $s);
            }
        }
    }

    private function uniqueSlug(string $base, EntityManagerInterface $em, ?int $excludeId = null): string
    {
        $repo = $em->getRepository(Product::class);
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
