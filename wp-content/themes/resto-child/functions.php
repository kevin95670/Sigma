<?php
// Protection de base : on arrête si le fichier est ouvert hors de WordPress.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Charge la feuille de style du thème enfant (style.css).
 *
 * « filemtime » donne la date de dernière modification du fichier : le
 * navigateur recharge la feuille de style à chaque modification, donc tes
 * changements apparaissent tout de suite.
 */
function resto_child_charger_styles() {

    $fichier = get_stylesheet_directory() . '/style.css';

    wp_enqueue_style(
        'resto-child',
        get_stylesheet_uri(),
        array(),
        file_exists( $fichier ) ? filemtime( $fichier ) : '0.2.0'
    );
}
add_action( 'wp_enqueue_scripts', 'resto_child_charger_styles', 20 );