<?php
/**
 * Plugin Name: Resto Core
 * Description: Fonctionnalités du restaurant Sigma : recettes, plats, réservations.
 * Version:     0.1.0
 * Author:      Kevin LEONARD
 * Text Domain: resto-core
 */

// Empêche l'accès direct au fichier.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'RESTO_CORE_PATH', plugin_dir_path( __FILE__ ) );

// On chargera ici un fichier par fonctionnalité.
require_once RESTO_CORE_PATH . 'includes/recettes.php';
require_once RESTO_CORE_PATH . 'includes/champ-recettes.php';
require_once RESTO_CORE_PATH . 'includes/ingredients.php';
require_once RESTO_CORE_PATH . 'includes/affichage.php';
require_once RESTO_CORE_PATH . 'includes/grille.php';
require_once RESTO_CORE_PATH . 'includes/plats.php';
require_once RESTO_CORE_PATH . 'includes/carte.php';
require_once RESTO_CORE_PATH . 'includes/reservations.php';
require_once RESTO_CORE_PATH . 'includes/mail.php';
require_once RESTO_CORE_PATH . 'includes/boutique.php';
require_once RESTO_CORE_PATH . 'includes/compte.php';
