<?php
// Protection de base : on arrête si le fichier est ouvert hors de WordPress.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shortcode [recettes] : affiche une grille de recettes.
 *
 * Exemples :
 *   [recettes]                       toutes les recettes (8 par page)
 *   [recettes type="sucree"]         seulement les sucrées
 *   [recettes type="salee"]          seulement les salées
 *   [recettes du_jour="1"]           seulement les « recettes du jour »
 *   [recettes nombre="4"]            au maximum 4 recettes, sans pagination
 *   [recettes barre="1"]             ajoute le nombre de recettes et le tri
 *   [recettes par_page="12"]         12 recettes par page au lieu de 8
 *
 * Sur la page « Recettes », on met : [recettes barre="1"].
 */
function resto_shortcode_recettes( $atts ) {

    // Les options acceptées, avec leur valeur par défaut.
    $atts = shortcode_atts(
        array(
            'type'     => '',     // identifiant du type : sucree, salee...
            'du_jour'  => '',     // « 1 » pour ne garder que les recettes du jour
            'nombre'   => -1,     // -1 = toutes ; un nombre = on s'arrête là, sans pagination
            'par_page' => 8,      // recettes par page quand il y a une pagination
            'barre'    => '',     // « 1 » pour afficher « 8 recettes » et le menu « Trier par »
        ),
        $atts,
        'recettes'
    );

    // 1. Quelles recettes récupérer (toutes celles qui correspondent ; on
    //    trie et on coupe en pages ensuite, en PHP).
    $args = array(
        'post_type'      => 'recette',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    // Filtre par type (sucrée / salée).
    if ( '' !== $atts['type'] ) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'type_recette',
                'field'    => 'slug',
                'terms'    => sanitize_title( $atts['type'] ),
            ),
        );
    }

    // Filtre « recette du jour » (la case cochée dans l'administration).
    if ( '1' === (string) $atts['du_jour'] ) {
        $args['meta_query'] = array(
            array(
                'key'   => '_recette_du_jour',
                'value' => '1',
            ),
        );
    }

    $recettes = get_posts( $args );

    if ( empty( $recettes ) ) {
        return '<p>Aucune recette pour le moment.</p>';
    }

    $total_recettes = count( $recettes );
    $avec_barre     = '1' === (string) $atts['barre'];

    // 2. Le tri choisi dans le menu « Trier par » (adresse : ?tri=temps).
    $tri = 'recent';
    if ( $avec_barre && isset( $_GET['tri'] ) ) {
        $demande = sanitize_key( wp_unslash( $_GET['tri'] ) );
        if ( in_array( $demande, array( 'recent', 'temps', 'difficulte' ), true ) ) {
            $tri = $demande;
        }
    }

    if ( 'temps' === $tri ) {
        // Du plus rapide au plus long.
        usort( $recettes, function ( $a, $b ) {
            return resto_temps_total( $a->ID ) <=> resto_temps_total( $b->ID );
        } );
    } elseif ( 'difficulte' === $tri ) {
        // De Facile à Difficile.
        usort( $recettes, function ( $a, $b ) {
            return resto_rang_difficulte( $a->ID ) <=> resto_rang_difficulte( $b->ID );
        } );
    }

    // 3. Découpage en pages (adresse : ?pg=2), sauf si « nombre » est donné.
    $nombre   = (int) $atts['nombre'];
    $par_page = max( 1, (int) $atts['par_page'] );
    $page     = 1;
    $pages    = 1;

    if ( $nombre > 0 ) {
        $recettes = array_slice( $recettes, 0, $nombre );
    } else {
        $pages = (int) ceil( $total_recettes / $par_page );
        $page  = isset( $_GET['pg'] ) ? min( $pages, max( 1, absint( $_GET['pg'] ) ) ) : 1;
        $recettes = array_slice( $recettes, ( $page - 1 ) * $par_page, $par_page );
    }

    // L'adresse de la page où se trouve le shortcode (pour les liens).
    $base = get_permalink();

    // On construit le HTML dans une « mémoire tampon », puis on le renvoie.
    ob_start();

    echo '<div class="resto-recettes">';

    // La barre : les filtres « Toutes / Sucrées / Salées » à gauche,
    // « 8 recettes » et « Trier par » à droite.
    if ( $avec_barre ) {
        echo '<div class="resto-recettes-barre">';
        echo resto_filtres_recettes();
        echo '<div class="resto-recettes-droite">';
        echo '<span class="resto-recettes-compte">' . esc_html( $total_recettes )
            . ' recette' . ( $total_recettes > 1 ? 's' : '' ) . '</span>';

        echo '<form class="resto-recettes-tri" method="get" action="' . esc_url( $base ) . '">';
        echo '<label for="resto-tri">Trier par</label>';
        echo '<select id="resto-tri" name="tri" onchange="this.form.submit()">';
        $choix = array(
            'recent'     => 'Plus récentes',
            'temps'      => 'Temps',
            'difficulte' => 'Difficulté',
        );
        foreach ( $choix as $valeur => $libelle ) {
            echo '<option value="' . esc_attr( $valeur ) . '"' . selected( $tri, $valeur, false ) . '>'
                . esc_html( $libelle ) . '</option>';
        }
        echo '</select>';
        echo '<noscript><button type="submit">OK</button></noscript>';
        echo '</form></div></div>';
    }

    echo '<div class="resto-recettes-grille">';

    foreach ( $recettes as $recette ) {
        echo resto_carte_recette( $recette->ID );
    }

    echo '</div>';

    // La pagination : « ‹ 1 2 3 › », en gardant le tri choisi.
    if ( $pages > 1 ) {

        $lien = function ( $numero ) use ( $base, $tri ) {
            $args = array( 'pg' => $numero );
            if ( 'recent' !== $tri ) {
                $args['tri'] = $tri;
            }
            return add_query_arg( $args, $base );
        };

        echo '<nav class="resto-recettes-pagination" aria-label="Pagination des recettes">';

        if ( $page > 1 ) {
            echo '<a class="resto-page-lien" href="' . esc_url( $lien( $page - 1 ) )
                . '" aria-label="Page précédente">‹</a>';
        }
        for ( $i = 1; $i <= $pages; $i++ ) {
            if ( $i === $page ) {
                echo '<span class="resto-page-lien resto-page-lien--actif" aria-current="page">' . $i . '</span>';
            } else {
                echo '<a class="resto-page-lien" href="' . esc_url( $lien( $i ) ) . '">' . $i . '</a>';
            }
        }
        if ( $page < $pages ) {
            echo '<a class="resto-page-lien" href="' . esc_url( $lien( $page + 1 ) )
                . '" aria-label="Page suivante">›</a>';
        }

        echo '</nav>';
    }

    echo '</div>';

    return ob_get_clean();
}
add_shortcode( 'recettes', 'resto_shortcode_recettes' );

/**
 * Le HTML d'une carte de recette (grille des recettes et « Vous aimerez aussi »).
 */
function resto_carte_recette( $id ) {
    ob_start();
    $total      = resto_temps_total( $id );
    $difficulte = get_post_meta( $id, '_difficulte', true );
    $budget     = get_post_meta( $id, '_budget', true );
    $du_jour    = '1' === get_post_meta( $id, '_recette_du_jour', true );
    $types      = get_the_terms( $id, 'type_recette' );
    $type       = ( is_array( $types ) && ! empty( $types ) ) ? $types[0] : null;

    echo '<a class="resto-recette-carte' . ( $du_jour ? ' resto-recette-carte--du-jour' : '' ) . '" href="' . esc_url( get_permalink( $id ) ) . '">';

    // L'image, avec la pastille de type (Sucrée / Salée) posée dessus.
    echo '<span class="resto-recette-image">';
    if ( has_post_thumbnail( $id ) ) {
        echo get_the_post_thumbnail( $id, 'medium_large' );
    }
    if ( $type ) {
        echo '<span class="resto-recette-type resto-recette-type--' . esc_attr( $type->slug ) . '">'
            . esc_html( $type->name ) . '</span>';
    }
    if ( $du_jour ) {
        echo '<span class="resto-recette-badge">★ Recette du jour</span>';
    }
    echo '</span>';

    // Le titre et les petites pastilles (temps, difficulté, budget).
    echo '<span class="resto-recette-corps">';
    echo '<span class="resto-recette-titre">' . esc_html( get_the_title( $id ) ) . '</span>';

    echo '<span class="resto-recette-infos">';
    if ( $total ) {
        echo '<span>' . esc_html( $total ) . ' min</span>';
    }
    if ( $difficulte ) {
        echo '<span>' . esc_html( $difficulte ) . '</span>';
    }
    if ( $budget ) {
        echo '<span>' . esc_html( $budget ) . '</span>';
    }
    echo '</span>';

    echo '<span class="resto-recette-lien">Voir la recette →</span>';

    echo '</span></a>';
    return ob_get_clean();
}

/**
 * Temps total d'une recette : préparation + cuisson, en minutes.
 */
function resto_temps_total( $id ) {
    return (int) get_post_meta( $id, '_temps_preparation', true )
         + (int) get_post_meta( $id, '_temps_cuisson', true );
}

/**
 * Un rang pour trier par difficulté : Facile = 1, Moyen = 2, Difficile = 3.
 * Une recette sans difficulté passe en dernier.
 */
function resto_rang_difficulte( $id ) {
    $rangs = array( 'Facile' => 1, 'Moyen' => 2, 'Difficile' => 3 );
    $valeur = get_post_meta( $id, '_difficulte', true );
    return isset( $rangs[ $valeur ] ) ? $rangs[ $valeur ] : 4;
}

/**
 * Les filtres « Toutes / Sucrées / Salées » au-dessus de la grille.
 *
 * Ce sont des liens vers tes vraies pages : la page « Recettes » (Toutes) et
 * ses pages enfants (Recettes sucrées, Recettes salées). Aucun réglage à faire :
 * si tu ajoutes une page enfant (par exemple « Recettes végétariennes »), un
 * filtre apparaît tout seul. La page où l'on se trouve est marquée « active ».
 */
function resto_filtres_recettes() {

    $courante = (int) get_queried_object_id();
    if ( ! $courante ) {
        return '';
    }

    // La page « Recettes » : la page courante, ou son parent si on est sur une enfant.
    $parent = (int) wp_get_post_parent_id( $courante );
    $racine = $parent ? $parent : $courante;

    $enfants = get_pages( array( 'parent' => $racine, 'post_status' => 'publish' ) );
    if ( empty( $enfants ) ) {
        return '';
    }

    // Sucrées avant Salées (puis l'ordre alphabétique pour les autres).
    usort( $enfants, function ( $a, $b ) {
        $rang = function ( $page ) {
            $titre = strtolower( remove_accents( $page->post_title ) );
            if ( false !== strpos( $titre, 'sucr' ) ) {
                return 0;
            }
            if ( false !== strpos( $titre, 'sal' ) ) {
                return 1;
            }
            return 2;
        };
        $ra = $rang( $a );
        $rb = $rang( $b );
        return ( $ra === $rb ) ? strcasecmp( $a->post_title, $b->post_title ) : $ra <=> $rb;
    } );

    // Un filtre : { lien, texte, actif }.
    $filtres = array(
        array( get_permalink( $racine ), 'Toutes', $courante === $racine ),
    );
    foreach ( $enfants as $enfant ) {
        // « Recettes sucrées » → « Sucrées ».
        $texte = preg_replace( '/^Recettes\s+/iu', '', $enfant->post_title );
        $texte = mb_strtoupper( mb_substr( $texte, 0, 1 ) ) . mb_substr( $texte, 1 );
        $filtres[] = array( get_permalink( $enfant->ID ), $texte, $courante === (int) $enfant->ID );
    }

    $html = '<nav class="resto-recettes-filtres" aria-label="Filtrer les recettes">';
    foreach ( $filtres as $filtre ) {
        $html .= '<a class="resto-filtre' . ( $filtre[2] ? ' resto-filtre--actif' : '' ) . '"'
            . ' href="' . esc_url( $filtre[0] ) . '"'
            . ( $filtre[2] ? ' aria-current="page"' : '' ) . '>'
            . esc_html( $filtre[1] ) . '</a>';
    }
    $html .= '</nav>';

    return $html;
}