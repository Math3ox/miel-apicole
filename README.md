# Miel Apicole — Rucher du Loison

Boutique en ligne de miel : Symfony 7 / PHP 8.3, MySQL 8, Twig + Tailwind v4, AssetMapper.

## Lancer en local

```bash
docker compose up -d --build          # ou podman-compose up -d
docker exec apiculteur_php composer install
docker exec apiculteur_php php bin/console doctrine:migrations:migrate -n
docker exec apiculteur_php npm install
```

| Service    | URL                    |
|------------|------------------------|
| Site       | http://localhost:8000  |
| phpMyAdmin | http://localhost:8081  |
| Mailpit    | http://localhost:8025  |

Tailwind en mode watch :
`docker exec -it apiculteur_php sh -c "./node_modules/.bin/tailwindcss -i ./assets/styles/app.css -o ./public/styles/app.css --watch"`

Tests : `docker exec apiculteur_php php bin/phpunit`

`vendor/` et `var/` vivent dans des volumes Docker (sinon 10 s+ par page sous Windows) : lancer `composer install` dans le conteneur, pas sur Windows.

## Config

- `.env` : valeurs par défaut (base des conteneurs). Pas de vrais secrets.
- `.env.local` (non versionné) : `APP_SECRET` et surcharges locales.

## Rôles

| Rôle              | Accès                                                         |
|-------------------|---------------------------------------------------------------|
| Client            | boutique, panier, commandes, avis, espace client              |
| `ROLE_APICULTEUR` | + back-office : produits, catégories, commandes, avis         |
| `ROLE_ADMIN`      | + gestion des utilisateurs et de leurs rôles                  |

Le rôle se change dans Admin › Utilisateurs. Premier admin, en SQL :
`UPDATE user SET roles='["ROLE_ADMIN"]' WHERE email='...';`

## À savoir

- Panier en session (`CartService`), plafonné au stock. Le stock est verrouillé pendant la commande.
- Un produit ou une variante déjà commandé ne peut pas être supprimé : passer son stock à 0.
- Mot de passe oublié : `symfonycasts/reset-password-bundle` (lien valable 1 h, une demande max toutes les 15 min). Config dans `config/packages/reset_password.yaml`.
- Factures PDF via Dompdf (`InvoiceGenerator`). Mails via Symfony Mailer (`MailerService`) et des templates dans `templates/emails/`.
- Notes de conception d'origine : `fichier.md`.
