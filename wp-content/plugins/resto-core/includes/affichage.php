<?php
// Protection de base : on arrête si le fichier est ouvert hors de WordPress.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Construit la fiche d'une recette autour du contenu de l'éditeur.
 *
 * Le filtre « the_content » reçoit le texte de la page (ici : les étapes
 * écrites dans l'éditeur) et peut le modifier avant l'affichage.
 * On y ajoute devant : les pastilles (temps, difficulté, budget, personnes)
 * et la liste des ingrédients.
 */
function resto_contenu_recette( $contenu ) {

    // On ne touche qu'à la page d'une recette, dans la boucle principale.
    if ( ! is_singular( 'recette' ) || ! in_the_loop() || ! is_main_query() ) {
        return $contenu;
    }

    $id = get_the_ID();

    // On relit les valeurs saisies dans l'administration.
    $preparation = (int) get_post_meta( $id, '_temps_preparation', true );
    $cuisson     = (int) get_post_meta( $id, '_temps_cuisson', true );
    $portions    = (int) get_post_meta( $id, '_portions', true );
    $difficulte  = get_post_meta( $id, '_difficulte', true );
    $budget      = get_post_meta( $id, '_budget', true );

    $html = '<div class="resto-fiche">';

    // 1. Les tuiles : Préparation, Cuisson, Personnes, Difficulté, Budget.
    //    On n'affiche que celles qui sont remplies.
    $tuiles = array(
        'Préparation' => $preparation ? $preparation . ' min' : '',
        'Cuisson'     => $cuisson ? $cuisson . ' min' : '',
        'Personnes'   => $portions ? (string) $portions : '',
        'Difficulté'  => $difficulte,
        'Budget'      => $budget,
    );

    $html_tuiles = '';
    foreach ( $tuiles as $libelle => $valeur ) {
        if ( '' !== $valeur ) {
            $html_tuiles .= '<li><span class="resto-tuile-label">' . esc_html( $libelle )
                . '</span><span class="resto-tuile-valeur">' . esc_html( $valeur ) . '</span></li>';
        }
    }
    if ( '' !== $html_tuiles ) {
        $html .= '<ul class="resto-tuiles">' . $html_tuiles . '</ul>';
    }

    // 3. La liste des ingrédients, avec leur image.
    $ingredients = get_post_meta( $id, '_ingredients', true );

    if ( is_array( $ingredients ) && ! empty( $ingredients ) ) {

        $html .= '<h2 class="resto-titre">Ingrédients</h2>';
        $html .= '<ul class="resto-ingredients">';

        foreach ( $ingredients as $ingredient ) {

            $html .= '<li>';

            if ( ! empty( $ingredient['image'] ) ) {
                $html .= wp_get_attachment_image(
                    (int) $ingredient['image'],
                    'thumbnail',
                    false,
                    array( 'class' => 'resto-ingredient-image' )
                );
            }

            $html .= '<span class="resto-ingredient-texte">';
            if ( ! empty( $ingredient['quantite'] ) ) {
                $html .= '<strong>' . esc_html( $ingredient['quantite'] ) . '</strong> ';
            }
            $html .= esc_html( isset( $ingredient['nom'] ) ? $ingredient['nom'] : '' );
            $html .= '</span></li>';
        }

        $html .= '</ul>';
    }

    // 4. Un titre, puis le contenu de l'éditeur : les étapes de préparation.
    $html .= '<h2 class="resto-titre">Préparation</h2>';
    $html .= '</div>';

    return $html . $contenu;
}
add_filter( 'the_content', 'resto_contenu_recette' );

/**
 * Après les étapes : le bandeau « Réserver une table » et trois autres
 * recettes (« Vous aimerez aussi »). Priorité 12 : on passe après la fiche.
 *
 * L'adresse de réservation se change avec le filtre « resto_url_reservation ».
 */
function resto_apres_recette( $contenu ) {

    if ( ! is_singular( 'recette' ) || ! in_the_loop() || ! is_main_query() ) {
        return $contenu;
    }

    $id = get_the_ID();

    $html  = '<div class="resto-reserver">';
    $html .= '<div><p class="resto-reserver-titre">Pas envie de cuisiner ?</p>';
    $html .= '<p class="resto-reserver-texte">Venez le déguster au restaurant, on vous garde une table.</p></div>';
    $html .= '<a class="resto-reserver-bouton" href="'
        . esc_url( apply_filters( 'resto_url_reservation', home_url( '/reservation/' ) ) )
        . '">Réserver une table</a>';
    $html .= '</div>';

    // Trois autres recettes : du même type d'abord, puis les plus récentes.
    $types  = wp_get_post_terms( $id, 'type_recette', array( 'fields' => 'ids' ) );
    $autres = array();
    if ( ! is_wp_error( $types ) && $types ) {
        $autres = get_posts( array(
            'post_type'      => 'recette',
            'post_status'    => 'publish',
            'posts_per_page' => 3,
            'post__not_in'   => array( $id ),
            'tax_query'      => array( array( 'taxonomy' => 'type_recette', 'field' => 'term_id', 'terms' => $types ) ),
        ) );
    }
    if ( count( $autres ) < 3 ) {
        $deja   = array_merge( array( $id ), wp_list_pluck( $autres, 'ID' ) );
        $autres = array_merge( $autres, get_posts( array(
            'post_type'      => 'recette',
            'post_status'    => 'publish',
            'posts_per_page' => 3 - count( $autres ),
            'post__not_in'   => $deja,
        ) ) );
    }

    if ( $autres ) {
        $html .= '<section class="resto-autres"><h2 class="resto-titre">Vous aimerez aussi</h2>';
        $html .= '<div class="resto-recettes-grille resto-recettes-grille--trois">';
        foreach ( $autres as $autre ) {
            $html .= resto_carte_recette( $autre->ID );
        }
        $html .= '</div></section>';
    }

    return $contenu . $html;
}
add_filter( 'the_content', 'resto_apres_recette', 12 );

/**
 * Fil d'Ariane d'une recette : Accueil / Recettes / Recettes salées / titre.
 *
 * Kadence ne connaît pas nos pages « Recettes » (une recette n'est pas une
 * page enfant) : il affiche seulement « Accueil / titre ». On remplace donc
 * son contenu, une fois la page chargée, par le bon chemin. Le style reste
 * celui de Kadence (classes .kadence-breadcrumbs et .bc-delimiter).
 */
function resto_fil_ariane_recette() {

    if ( ! is_singular( 'recette' ) ) {
        return;
    }

    $chemin = array( array( 'Accueil', home_url( '/' ) ) );

    // La page « Recettes » (adresse /recettes/).
    $racine = get_page_by_path( 'recettes' );
    if ( $racine ) {
        $chemin[] = array( get_the_title( $racine ), get_permalink( $racine ) );

        // La page enfant qui correspond au type : « sucrée » ou « salée ».
        $types = get_the_terms( get_the_ID(), 'type_recette' );
        if ( is_array( $types ) && ! empty( $types ) ) {
            $nom = strtolower( remove_accents( $types[0]->name ) );
            $mot = ( false !== strpos( $nom, 'sucr' ) ) ? 'sucr' : ( ( false !== strpos( $nom, 'sal' ) ) ? 'sal' : '' );
            if ( $mot ) {
                foreach ( get_pages( array( 'parent' => $racine->ID, 'post_status' => 'publish' ) ) as $enfant ) {
                    if ( false !== strpos( strtolower( remove_accents( $enfant->post_title ) ), $mot ) ) {
                        $chemin[] = array( $enfant->post_title, get_permalink( $enfant ) );
                        break;
                    }
                }
            }
        }
    }

    // Le HTML du chemin, avec le titre de la recette en dernier (sans lien).
    $html = '';
    foreach ( $chemin as $etape ) {
        $html .= '<span><a href="' . esc_url( $etape[1] ) . '">' . esc_html( $etape[0] ) . '</a></span>'
            . '<span class="bc-delimiter">/</span>';
    }
    $html .= '<span class="kadence-bread-current">' . esc_html( get_the_title() ) . '</span>';

    ?>
    <script>
    (function () {
        var fil = document.querySelector('.kadence-breadcrumbs .kadence-breadcrumb-container')
               || document.querySelector('.kadence-breadcrumbs');
        if (fil) {
            fil.innerHTML = <?php echo wp_json_encode( $html ); ?>;
        }
    })();
    </script>
    <?php
}
add_action( 'wp_footer', 'resto_fil_ariane_recette' );