<?php
// Protection de base : on arrête si le fichier est ouvert hors de WordPress.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Boutique : la rangée de filtres « Tout / Plats à emporter / Desserts… »
 * au-dessus de la grille des produits.
 *
 * WooCommerce n'en propose pas de base. On affiche un lien vers chaque
 * catégorie de produits (les catégories vides sont ignorées), et la
 * catégorie courante est marquée « active ». Une catégorie créée plus tard
 * apparaît toute seule.
 */
function resto_filtres_boutique() {

    // Seulement sur la boutique et sur les pages de catégories.
    if ( ! function_exists( 'is_shop' ) || ( ! is_shop() && ! is_product_category() ) ) {
        return;
    }

    $categories = get_terms( array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => true,
        'exclude'    => array( (int) get_option( 'default_product_cat' ) ), // « Non classé »
        'orderby'    => 'term_id',
        'order'      => 'ASC',
    ) );

    if ( is_wp_error( $categories ) || empty( $categories ) ) {
        return;
    }

    $GLOBALS['resto_barre_boutique_ouverte'] = true;

    $courante = is_product_category() ? (int) get_queried_object_id() : 0;

    // Une seule ligne : les filtres à gauche, « 10 résultats » et « Trier par » à droite.
    // On ouvre ici la ligne et son bloc de droite ; resto_fermer_barre_boutique() les ferme
    // une fois que WooCommerce et Kadence ont affiché le nombre de résultats et le tri.
    echo '<div class="resto-boutique-barre">';

    echo '<nav class="resto-filtres-boutique" aria-label="Filtrer les produits">';

    echo '<a class="resto-filtre' . ( is_shop() ? ' resto-filtre--actif' : '' ) . '"'
        . ' href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">Tout</a>';

    foreach ( $categories as $categorie ) {
        $actif = ( $courante === (int) $categorie->term_id );
        echo '<a class="resto-filtre' . ( $actif ? ' resto-filtre--actif' : '' ) . '"'
            . ' href="' . esc_url( get_term_link( $categorie ) ) . '"'
            . ( $actif ? ' aria-current="page"' : '' ) . '>'
            . esc_html( $categorie->name ) . '</a>';
    }

    echo '</nav>';

    echo '<div class="resto-boutique-droite">';
}
add_action( 'woocommerce_before_shop_loop', 'resto_filtres_boutique', 5 );

/**
 * Ferme la ligne ouverte plus haut (priorité 40 : après le nombre de résultats
 * et le tri de WooCommerce, et après les boutons de Kadence).
 */
function resto_fermer_barre_boutique() {

    if ( ! function_exists( 'is_shop' ) || ( ! is_shop() && ! is_product_category() ) ) {
        return;
    }
    if ( ! empty( $GLOBALS['resto_barre_boutique_ouverte'] ) ) {
        echo '</div></div>';
        $GLOBALS['resto_barre_boutique_ouverte'] = false;
    }
}
add_action( 'woocommerce_before_shop_loop', 'resto_fermer_barre_boutique', 40 );