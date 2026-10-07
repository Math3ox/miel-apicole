# Feuille de route — avant et après l'ouverture

État au 7 octobre 2026. Cocher au fur et à mesure.

## 🔴 Indispensable avant d'ouvrir au public

- [ ] **Frais de livraison** — aujourd'hui jamais calculés : le panier dit « calculée à l'étape suivante » et la facture affiche « Offerte ».
  - Tarif selon le poids (Colissimo, Mondial Relay…)
  - Option : livraison offerte au-delà d'un montant
  - Option **« retrait au rucher »** gratuite
- [ ] **Pages légales** — les liens du pied de page pointent vers `#`.
  - Mentions légales (nom, SIRET, adresse, hébergeur)
  - CGV : prix, livraison, droit de rétractation 14 jours (exception : pot dont l'opercule a été ouvert)
  - Politique de confidentialité (RGPD). Pas de bandeau cookies nécessaire tant qu'il n'y a pas de cookies de suivi.
- [ ] **Facture conforme** (`templates/invoice/facture.html.twig`)
  - Ajouter SIRET, adresse, mention TVA (micro-entreprise : « TVA non applicable, art. 293 B du CGI »)
  - Numérotation **continue, sans trou** : numéro de facture séparé, attribué au paiement (les commandes abandonnées créent des trous dans les numéros de commande)
- [ ] **Vrais mails** — service d'envoi (ex. Brevo, gratuit jusqu'à 300 mails par jour) + SPF/DKIM sur le domaine, sinon les mails partent en spam
- [ ] **Mise en ligne** — serveur, nom de domaine, HTTPS, sauvegarde automatique de la base, webhook Stripe déclaré, cron `php bin/console app:payments:sync` toutes les 15 min, clés Stripe en mode live

## 🟠 Important dans les premières semaines

- [ ] **Remboursement automatique** — annuler une commande payée remet le stock mais ne rembourse pas (à faire à la main dans Stripe pour l'instant)
- [ ] **Suivi d'expédition** — champ « numéro de suivi » + mail « Votre colis est parti » au passage en « Expédiée »
- [ ] **Vérification de l'email à l'inscription** — évite les faux comptes, permet ensuite de rattacher les commandes invité à un compte
- [ ] **Tableau de bord admin** — chiffre d'affaires du mois, commandes à expédier, stocks bas (l'accueil admin redirige aujourd'hui vers les produits)
- [ ] **Tests automatiques** — seulement 5 tests (inscription, connexion, en-têtes) ; ajouter paiement, stock, panier

## 🟢 Pour faire décoller les ventes

- [ ] **Avis « achat vérifié »** — aujourd'hui tout client connecté peut noter un produit sans l'avoir acheté
- [ ] **Codes promo** — Stripe les gère presque seul
- [ ] **Fiches produit plus riches** — plusieurs photos, origine des fleurs, date de récolte
- [ ] **Newsletter** — prévenir des nouvelles récoltes
- [ ] **Statistiques de visite** respectueuses du RGPD (Plausible, Matomo), sans bandeau cookies

## Infos nécessaires pour avancer

- Statut juridique (micro-entreprise ?), SIRET, adresse → pages légales et facture
- Transporteur et grille de tarifs souhaités, retrait au rucher oui/non → livraison
- Clé Stripe de test dans `.env.local` → test du paiement de bout en bout
