# Projet : Miel Apicole — E-commerce Symfony 7

## Stack technique
- Backend : PHP 8.3 / Symfony 7
- BDD : MySQL 8.0 via Doctrine ORM
- Front : Twig + Tailwind CSS v4
- Conteneurs : Podman (PHP-FPM, Nginx, MySQL, phpMyAdmin)
- Node : AssetMapper (pas Webpack)

## Règles absolues
- INTERDICTION d'utiliser make:auth ou tout outil auto-généré de Symfony pour l'authentification
- INTERDICTION d'utiliser les outils Symfony auto-générés pour l'envoi de mails
- Tout doit être codé manuellement (controllers, formulaires, sessions, mailer)

## Entités Doctrine existantes
- User (id, email, password, roles, firstName, lastName, createdAt)
- Product (id, name, slug, description, price, image, tastingAdvice, allergens, isBestSeller, isOnSale, createdAt, category, variants, reviews)
- Category (id, name, slug, description, products)
- ProductVariant (id, weight, price, stock, product)
- Order (id, status, totalPrice, createdAt, updatedAt, user, orderItems)
- OrderItem (id, quantity, unitPrice, weight, orderRef, productVariant)
- Address (id, firstName, lastName, street, city, postalCode, country, isDefault, user)
- Review (id, rating, comment, isApproved, createdAt, product, user)

## Panier
- Géré en session (pas d'entité CartItem)

## Structure conteneurs
- apiculteur_php : PHP 8.3-fpm (port interne)
- apiculteur_nginx : Nginx (port 8000)
- apiculteur_mysql : MySQL 8.0 (port 3306)
- apiculteur_phpmyadmin : phpMyAdmin (port 8081)

## Commandes utiles
- Lancer les conteneurs : podman-compose up -d
- Console Symfony : podman exec -it apiculteur_php php bin/console
- Tailwind watcher : podman exec -it apiculteur_php bash -c "cd /var/www/html && ./node_modules/.bin/tailwindcss -i ./assets/styles/app.css -o ./public/styles/app.css --watch"

## Fonctionnalités à développer (par ordre de priorité)
1. Layout de base (base.html.twig, header, footer)
2. Authentification manuelle (register, login, logout, rôles ROLE_USER / ROLE_ADMIN)
3. CRUD produits admin (upload images, variantes, catégories)
4. Catalogue + fiche produit
5. Panier (session)
6. Checkout + commande
7. Facture PDF
8. Espace client
9. Gestion commandes admin
10. Avis produits + modération
11. Mails manuels (Mailer + templates Twig)
12. SEO, responsive, sécurité
13. CI GitHub Actions