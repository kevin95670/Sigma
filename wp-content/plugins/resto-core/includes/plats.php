<?php
// Protection de base : on arrête si le fichier est ouvert hors de WordPress.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Le type de contenu « Plat » : un élément de la carte du restaurant.
 *
 * Ce n'est PAS une recette : un plat a un prix et une catégorie de carte
 * (entrée, plat, dessert, boisson), mais ni ingrédients ni étapes.
 * Il peut être relié à une recette (champ facultatif « Recette associée »).
 *
 * Contenu d'un plat :
 *   - le titre WordPress          → le nom du plat
 *   - l'« extrait » WordPress     → la courte description
 *   - les champs ci-dessous       → prix, pastilles, recette associée
 *   - la catégorie de carte       → Entrées, Plats, Desserts, Boissons
 */

/**
 * Affiche un prix proprement : « 17 » pour un prix rond, « 12,50 » sinon.
 */
function resto_formater_prix( $prix ) {
    $prix = (float) $prix;
    $rond = ( floor( $prix ) === $prix );
    return number_format_i18n( $prix, $rond ? 0 : 2 );
}

/**
 * 1. Déclare le type de contenu « Plat » et sa catégorie de carte.
 */
function resto_declarer_plats() {

    register_post_type( 'plat', array(
        'labels' => array(
            'name'          => 'Plats',
            'singular_name' => 'Plat',
            'add_new'       => 'Ajouter un plat',
            'add_new_item'  => 'Ajouter un plat',
            'edit_item'     => 'Modifier le plat',
            'new_item'      => 'Nouveau plat',
            'view_item'     => 'Voir le plat',
            'search_items'  => 'Rechercher un plat',
            'not_found'     => 'Aucun plat trouvé',
            'all_items'     => 'Tous les plats',
            'menu_name'     => 'La carte',
        ),
        // Pas de page propre pour un plat : il s'affiche seulement sur la page
        // « La carte » (shortcode à venir). On garde l'écran d'administration.
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_rest'       => true,   // éditeur de blocs
        'has_archive'        => false,
        'menu_icon'          => 'dashicons-food',
        'menu_position'      => 21,
        // titre = nom du plat, extrait = courte description, image facultative
        'supports'           => array( 'title', 'excerpt', 'thumbnail' ),
    ) );

    register_taxonomy( 'categorie_plat', 'plat', array(
        'labels' => array(
            'name'          => 'Catégories de la carte',
            'singular_name' => 'Catégorie de la carte',
            'add_new_item'  => 'Ajouter une catégorie',
            'edit_item'     => 'Modifier la catégorie',
            'all_items'     => 'Toutes les catégories',
            'search_items'  => 'Rechercher une catégorie',
            'menu_name'     => 'Catégories',
        ),
        'public'            => false,
        'show_ui'           => true,
        'show_in_rest'      => true,
        'hierarchical'      => true,    // cases à cocher (comme les catégories d'articles)
        'show_admin_column' => true,    // colonne dans la liste des plats
    ) );
}
add_action( 'init', 'resto_declarer_plats' );

/**
 * 2. Crée une seule fois les quatre catégories de départ.
 *    Les identifiants (slugs) servent plus tard au shortcode : entrees, plats,
 *    desserts, boissons. On crée dans cet ordre : c'est l'ordre d'affichage.
 */
function resto_creer_categories_plat() {

    // Déjà fait ? On ne refait rien (et on ne recrée pas ce que tu supprimes).
    if ( get_option( 'resto_categories_plat_creees' ) ) {
        return;
    }

    $categories = array(
        'entrees'  => 'Entrées',
        'plats'    => 'Plats',
        'desserts' => 'Desserts',
        'boissons' => 'Boissons',
    );

    foreach ( $categories as $slug => $nom ) {
        if ( ! term_exists( $slug, 'categorie_plat' ) ) {
            wp_insert_term( $nom, 'categorie_plat', array( 'slug' => $slug ) );
        }
    }

    update_option( 'resto_categories_plat_creees', '1' );
}
add_action( 'init', 'resto_creer_categories_plat', 20 );

/**
 * 3. La liste des champs d'un plat.
 *    Pour en ajouter un plus tard, il suffit d'ajouter une entrée ici.
 */
function resto_champs_plat() {
    return array(

        // Le prix en euros. On accepte les centimes (12,50).
        'prix' => array(
            'label' => 'Prix (€)',
            'type'  => 'price',
        ),

        // Les deux pastilles de la carte.
        'vegetarien' => array(
            'label' => 'Pastille « Végétarien »',
            'type'  => 'checkbox',
        ),
        'signature' => array(
            'label' => 'Pastille « Signature »',
            'type'  => 'checkbox',
        ),

        // FACULTATIF : relie le plat à l'une de tes recettes. Quand il est
        // rempli, la carte affichera « Voir la recette », et la fiche de la
        // recette affichera « Au menu du restaurant ».
        'recette_associee' => array(
            'label' => 'Recette associée (facultatif)',
            'type'  => 'recette',
        ),
    );
}

/**
 * 4. Ajoute la boîte « Détails du plat » sur l'écran d'édition.
 */
function resto_ajouter_boite_plat() {
    add_meta_box(
        'resto_plat_champs',
        'Détails du plat',
        'resto_afficher_boite_plat',
        'plat',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'resto_ajouter_boite_plat' );

/**
 * Dessine le formulaire : une ligne par champ.
 */
function resto_afficher_boite_plat( $post ) {

    wp_nonce_field( 'resto_sauver_plat', 'resto_plat_nonce' );

    foreach ( resto_champs_plat() as $cle => $champ ) {

        $valeur = get_post_meta( $post->ID, '_' . $cle, true );

        echo '<p><label for="' . esc_attr( $cle ) . '"><strong>'
            . esc_html( $champ['label'] ) . '</strong></label><br>';

        switch ( $champ['type'] ) {

            case 'price':
                // type="text" plutôt que "number" : plus simple pour saisir « 12,50 ».
                echo '<input type="text" inputmode="decimal" size="8" id="' . esc_attr( $cle )
                    . '" name="' . esc_attr( $cle )
                    . '" value="' . esc_attr( $valeur ) . '"> €';
                break;

            case 'checkbox':
                echo '<input type="checkbox" id="' . esc_attr( $cle )
                    . '" name="' . esc_attr( $cle ) . '" value="1"'
                    . checked( $valeur, '1', false ) . '> Oui';
                break;

            case 'recette':
                // La liste de toutes les recettes, par ordre alphabétique,
                // avec « Aucune » en premier : le champ reste facultatif.
                $recettes = get_posts( array(
                    'post_type'      => 'recette',
                    'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                ) );
                echo '<select id="' . esc_attr( $cle ) . '" name="' . esc_attr( $cle ) . '">';
                echo '<option value="">Aucune</option>';
                foreach ( $recettes as $recette ) {
                    echo '<option value="' . esc_attr( $recette->ID ) . '"'
                        . selected( (int) $valeur, $recette->ID, false ) . '>'
                        . esc_html( $recette->post_title ) . '</option>';
                }
                echo '</select>';
                break;
        }

        echo '</p>';
    }

    echo '<p class="description">Nom du plat = titre ci-dessus. Courte description = « Extrait » '
        . '(à droite ou en bas de l\'écran). Catégorie = encadré « Catégories de la carte ».</p>';
}

/**
 * 5. Enregistre les valeurs quand on clique sur « Mettre à jour ».
 */
function resto_sauver_champs_plat( $post_id ) {

    // Sécurité : jeton, enregistrement automatique, droits.
    if ( ! isset( $_POST['resto_plat_nonce'] )
        || ! wp_verify_nonce( $_POST['resto_plat_nonce'], 'resto_sauver_plat' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    foreach ( resto_champs_plat() as $cle => $champ ) {

        switch ( $champ['type'] ) {

            case 'price':
                // « 12,50 » → « 12.50 » ; on garde seulement un nombre positif.
                $brut   = isset( $_POST[ $cle ] ) ? wp_unslash( $_POST[ $cle ] ) : '';
                $brut   = str_replace( ',', '.', trim( $brut ) );
                $valeur = is_numeric( $brut ) ? (string) abs( (float) $brut ) : '';
                break;

            case 'checkbox':
                $valeur = isset( $_POST[ $cle ] ) ? '1' : '0';
                break;

            case 'recette':
                // On garde l'identifiant seulement si c'est bien une recette.
                $id     = isset( $_POST[ $cle ] ) ? absint( $_POST[ $cle ] ) : 0;
                $valeur = ( $id && 'recette' === get_post_type( $id ) ) ? $id : '';
                break;

            default:
                $valeur = '';
        }

        update_post_meta( $post_id, '_' . $cle, $valeur );
    }
}
add_action( 'save_post_plat', 'resto_sauver_champs_plat' );

/**
 * 6. Liste des plats dans l'administration : deux colonnes en plus (prix et
 *    recette associée), pour voir d'un coup d'œil toute la carte.
 */
function resto_colonnes_plats( $colonnes ) {
    $colonnes['resto_prix']    = 'Prix';
    $colonnes['resto_recette'] = 'Recette associée';
    return $colonnes;
}
add_filter( 'manage_plat_posts_columns', 'resto_colonnes_plats' );

function resto_remplir_colonnes_plats( $colonne, $post_id ) {

    if ( 'resto_prix' === $colonne ) {
        $prix = get_post_meta( $post_id, '_prix', true );
        echo ( '' !== $prix ) ? esc_html( resto_formater_prix( $prix ) ) . ' €' : '—';
    }

    if ( 'resto_recette' === $colonne ) {
        $recette = (int) get_post_meta( $post_id, '_recette_associee', true );
        echo $recette ? esc_html( get_the_title( $recette ) ) : '—';
    }
}
add_action( 'manage_plat_posts_custom_column', 'resto_remplir_colonnes_plats', 10, 2 );

/**
 * 7. Sens inverse : sur la fiche d'une recette, on affiche « Au menu du
 *    restaurant : 17 € » quand un plat y est relié.
 *
 *    Priorité 11 : on passe APRÈS la fiche de la recette (priorité 10, dans
 *    affichage.php) et on place cette ligne tout en haut.
 */
function resto_ligne_au_menu( $contenu ) {

    if ( ! is_singular( 'recette' ) || ! in_the_loop() || ! is_main_query() ) {
        return $contenu;
    }

    // Le plat relié à cette recette (le premier trouvé, s'il y en a un).
    $plats = get_posts( array(
        'post_type'      => 'plat',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'meta_key'       => '_recette_associee',
        'meta_value'     => get_the_ID(),
    ) );

    if ( empty( $plats ) ) {
        return $contenu;
    }

    $prix = get_post_meta( $plats[0]->ID, '_prix', true );

    $texte = 'Au menu du restaurant';
    if ( '' !== $prix ) {
        $texte .= ' : ' . resto_formater_prix( $prix ) . ' €';
    }

    return '<p class="resto-au-menu">' . esc_html( $texte ) . '</p>' . $contenu;
}
add_filter( 'the_content', 'resto_ligne_au_menu', 11 );