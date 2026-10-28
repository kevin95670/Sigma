<?php
// Protection de base : on arrête si le fichier est ouvert hors de WordPress.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shortcode [carte] : affiche la carte du restaurant, une section par
 * catégorie (Entrées, Plats, Desserts, Boissons).
 *
 * Exemples :
 *   [carte]                        toute la carte
 *   [carte categorie="desserts"]   seulement les desserts
 *
 * Ce fichier ne fait que le HTML ; la mise en forme (points de conduite,
 * pastilles, colonnes) est dans le style.css du thème enfant.
 */
function resto_shortcode_carte( $atts ) {

    $atts = shortcode_atts(
        array(
            'categorie' => '',   // identifiant d'une catégorie : entrees, plats...
        ),
        $atts,
        'carte'
    );

    // Le mot affiché à côté du nombre : « 6 plats », « 4 douceurs », « 4 boissons ».
    $mots = array(
        'entrees'  => 'plat',
        'plats'    => 'plat',
        'desserts' => 'douceur',
        'boissons' => 'boisson',
    );

    // Les catégories, dans l'ordre où elles ont été créées (Entrées, Plats,
    // Desserts, Boissons). On saute celles qui n'ont aucun plat.
    $args_categories = array(
        'taxonomy'   => 'categorie_plat',
        'orderby'    => 'term_id',
        'order'      => 'ASC',
        'hide_empty' => true,
    );
    if ( '' !== $atts['categorie'] ) {
        $args_categories['slug'] = sanitize_title( $atts['categorie'] );
    }
    $categories = get_terms( $args_categories );

    if ( is_wp_error( $categories ) || empty( $categories ) ) {
        return '<p>La carte arrive bientôt.</p>';
    }

    ob_start();

    echo '<div class="resto-menu">';

    foreach ( $categories as $categorie ) {

        // Les plats de cette catégorie, dans l'ordre où tu les as créés.
        $plats = get_posts( array(
            'post_type'      => 'plat',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'tax_query'      => array(
                array(
                    'taxonomy' => 'categorie_plat',
                    'field'    => 'term_id',
                    'terms'    => $categorie->term_id,
                ),
            ),
        ) );

        if ( empty( $plats ) ) {
            continue;
        }

        // « 1 plat » / « 6 plats ».
        $nombre = count( $plats );
        $mot    = isset( $mots[ $categorie->slug ] ) ? $mots[ $categorie->slug ] : 'plat';
        $compte = $nombre . ' ' . $mot . ( $nombre > 1 ? 's' : '' );

        // L'identifiant sert de lien d'ancre : /la-carte/#desserts
        echo '<section class="resto-menu-section" id="' . esc_attr( $categorie->slug ) . '">';

        echo '<div class="resto-menu-entete">';
        echo '<h2 class="resto-menu-titre">' . esc_html( $categorie->name ) . '</h2>';
        echo '<span class="resto-menu-trait" aria-hidden="true"></span>';
        echo '<span class="resto-menu-compte">' . esc_html( $compte ) . '</span>';
        echo '</div>';

        echo '<div class="resto-menu-liste">';

        foreach ( $plats as $plat ) {

            $prix       = get_post_meta( $plat->ID, '_prix', true );
            $vegetarien = '1' === get_post_meta( $plat->ID, '_vegetarien', true );
            $signature  = '1' === get_post_meta( $plat->ID, '_signature', true );
            $recette_id = (int) get_post_meta( $plat->ID, '_recette_associee', true );

            echo '<div class="resto-plat">';

            // Ligne du haut : nom, pastilles, points de conduite, prix.
            echo '<div class="resto-plat-ligne">';
            echo '<span class="resto-plat-nom">' . esc_html( get_the_title( $plat ) ) . '</span>';
            if ( $vegetarien ) {
                echo '<span class="resto-plat-pastille resto-plat-pastille--vegetarien">Végétarien</span>';
            }
            if ( $signature ) {
                echo '<span class="resto-plat-pastille resto-plat-pastille--signature">Signature</span>';
            }
            echo '<span class="resto-plat-points" aria-hidden="true"></span>';
            if ( '' !== $prix ) {
                echo '<span class="resto-plat-prix">' . esc_html( resto_formater_prix( $prix ) ) . '&nbsp;€</span>';
            }
            echo '</div>';

            // La courte description (l'« extrait » du plat).
            if ( '' !== trim( $plat->post_excerpt ) ) {
                echo '<p class="resto-plat-description">' . esc_html( $plat->post_excerpt ) . '</p>';
            }

            // Lien vers la recette, seulement si elle est reliée ET publiée.
            if ( $recette_id && 'publish' === get_post_status( $recette_id ) ) {
                echo '<a class="resto-plat-recette" href="' . esc_url( get_permalink( $recette_id ) )
                    . '">Voir la recette →</a>';
            }

            echo '</div>';
        }

        echo '</div></section>';
    }

    echo '</div>';

    return ob_get_clean();
}
add_shortcode( 'carte', 'resto_shortcode_carte' );