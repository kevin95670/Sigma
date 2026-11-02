# Sigma — site de restaurant WordPress / WooCommerce

**Sigma** est un restaurant **fictif**, créé pour apprendre et montrer ce que je sais faire avec WordPress : un site complet, du design au déploiement.

> ⚠️ Projet de démonstration : aucune réservation n'est honorée, aucune commande n'est livrée et **aucun paiement réel n'est effectué**. Ne saisissez pas de vraies données personnelles.

**Démo en ligne :** <https://sigma.kevinleonard.fr>

<!-- Ajouter ici 2 ou 3 captures d'écran (accueil, carte, boutique), par exemple :
![Accueil](docs/accueil.png)
-->

## Ce que fait le site

- **Accueil** : slider « Recette du jour », atouts, aperçu de la carte, recettes et boutique.
- **La carte** : entrées, plats et desserts, gérés depuis l'administration.
- **Réservation de table** : choix de la date, du service et du nombre de personnes, avec e-mail de confirmation (pas de paiement).
- **Recettes** : grille filtrable (sucrées / salées), tri par temps ou difficulté, pagination, et une page par recette (ingrédients, étapes, temps, difficulté, budget). Les recettes sont « à refaire chez soi ».
- **Boutique WooCommerce** : plats à emporter, accessoires, panier, validation de commande, codes promo, retrait au restaurant ou livraison, compte client. Les paiements sont **fictifs** (virement, paiement au restaurant).
- **Pages légales** : mentions légales, CGV, politique de confidentialité (données fictives).
- **Responsive** : ordinateur, tablette et mobile.
- **SEO de base** (Rank Math) et performances (LiteSpeed Cache).

## Pile technique

| Rôle | Outil |
|---|---|
| CMS | WordPress 7 (PHP 8.3), MariaDB |
| Thème | Kadence (thème parent) + thème enfant **`resto-child`** |
| Pages | Éditeur de blocs (Kadence Blocks) ; Elementor pour l'accueil uniquement ; Smart Slider 3 pour le slider |
| E-commerce | WooCommerce |
| Formulaires | WPForms Lite (contact) + réservation faite maison |
| Code maison | Plugin **`resto-core`** (PHP, JS) |
| Environnement local | Docker Compose (WordPress, MariaDB, phpMyAdmin, Mailpit) |
| Versionnement | Git / GitHub |
| Déploiement | GitHub Actions vers Hostinger |

## Ce que j'ai codé moi-même

Seul **mon code** est versionné ; WordPress et les plugins du commerce sont installés à part.

- **`wp-content/themes/resto-child/`** : thème enfant de Kadence. `style.css` regroupe tout le CSS spécifique (en-tête, footer, grille de recettes, boutique, panier, validation, Mon compte, sliders, accueil) et `functions.php` charge les styles.
- **`wp-content/plugins/resto-core/`** : le plugin du site.
  - `plats.php`, `carte.php` : la carte (type de contenu et shortcode `[carte]`).
  - `champs-recettes.php`, `ingredients.php`, `affichage.php`, `grille.php` : recettes (champs, ingrédients, page d'une recette, shortcodes `[recettes]` et `[filtres_recettes]`).
  - `reservations.php` + `assets/reservation.js` : réservation de table (formulaire, créneaux, e-mails, gestion dans l'administration), shortcode `[reservation]`.
  - `boutique.php`, `compte.php` : ajustements WooCommerce et icône « Mon compte » dans l'en-tête.
  - `mail.php` : envoie les e-mails vers Mailpit **en local seulement**.

## Lancer le projet en local

Prérequis : [Docker](https://www.docker.com/) et Docker Compose.

1. Créer un fichier `.env` à la racine, avec ces variables (choisir ses propres valeurs) :
   ```
   DB_NAME=sigma
   DB_USER=sigma
   DB_PASSWORD=...
   DB_ROOT_PASSWORD=...
   ```
2. Démarrer :
   ```
   docker compose up -d --build
   ```
3. Ouvrir <http://localhost:8080> et suivre l'installation de WordPress.
4. Installer et activer le thème **Kadence**, puis activer le thème enfant **resto-child** et le plugin **resto-core** (les deux sont montés depuis ce dépôt).

| Service | Adresse |
|---|---|
| Site | <http://localhost:8080> |
| phpMyAdmin | <http://localhost:8081> |
| Mailpit (e-mails de test) | <http://localhost:8025> |

En local, tous les e-mails (réservations, commandes, contact) arrivent dans Mailpit, aucun e-mail réel n'est envoyé.

## Déploiement

À chaque `push` sur la branche `master`, le workflow `.github/workflows/deploy.yml` envoie par **rsync sur SSH** le thème enfant et le plugin sur l'hébergement. Il ne touche ni à WordPress, ni aux plugins tiers, ni aux images.

Secrets GitHub à définir : `SSH_HOST`, `SSH_PORT`, `SSH_USER`, `SSH_PATH`, `SSH_PRIVATE_KEY` (clé SSH dédiée au déploiement, sans phrase secrète).

Le contenu (pages, produits, réglages) n'est pas dans Git : il a été transféré une fois du site local vers le site en ligne avec un plugin de migration.

## Choix et limites

- **Aucun paiement réel** : la boutique utilise des modes de paiement fictifs pour montrer le parcours complet d'une commande.
- **Natif d'abord** : je privilégie les réglages de WordPress, de Kadence et de WooCommerce ; je n'ajoute du CSS ou du PHP que lorsque le natif ne suffit pas, et le PHP va dans le plugin, jamais dans le thème.
- **Elementor** est limité à l'accueil, pour les blocs que l'éditeur de blocs fait mal ; le reste est en blocs natifs.
- Le site n'est **pas indexé** par les moteurs de recherche (c'est une démo).
- Pas encore de contenu de démo importable ni d'installateur automatique : le contenu est saisi à la main.

## Utilisation d'une IA

J'ai été aidé par une IA (Claude, d'Anthropic) comme assistant pour ce projet : explications, relectures, propositions de code et de CSS. Je choisis les fonctionnalités, je teste chaque étape, et je relis et adapte le code que j'intègre.

## Auteur

**Kevin Leonard** — développeur PHP / WordPress & intégrateur web  
Portfolio : <https://kevinleonard.fr>

## Licence

Code du thème enfant et du plugin : tous droits réservés, pour consultation et démonstration. Le logo, les textes et les photos de recettes sont des éléments de démonstration.
