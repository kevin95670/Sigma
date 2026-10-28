<?php
// Protection de base : on arrête si le fichier est ouvert hors de WordPress.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * La liste de TOUS les champs de la fiche.
 * Pour en ajouter un plus tard, il suffit d'ajouter une entrée ici.
 * Chaque champ a : un libellé (label) et un type d'affichage (type).
 */
function resto_champs_recette() {
    return array(

        // Durée de préparation, en minutes. Un nombre : on pourra l'additionner
        // avec la cuisson pour afficher le temps total.
        'temps_preparation' => array(
            'label' => 'Temps de préparation (minutes)',
            'type'  => 'number',
        ),

        // Durée de cuisson, en minutes. Même principe.
        'temps_cuisson' => array(
            'label' => 'Temps de cuisson (minutes)',
            'type'  => 'number',
        ),

        // Nombre de personnes pour lesquelles la recette est prévue.
        'portions' => array(
            'label' => 'Nombre de personnes',
            'type'  => 'number',
        ),

        // Difficulté : une liste de choix fermée, pour que toutes les fiches
        // utilisent exactement les mêmes mots.
        'difficulte' => array(
            'label'   => 'Difficulté',
            'type'    => 'select',
            'options' => array( 'Facile', 'Moyen', 'Difficile' ),
        ),

        // Budget : même principe que la difficulté.
        'budget' => array(
            'label'   => 'Budget',
            'type'    => 'select',
            'options' => array( 'Bon marché', 'Moyen', 'Cher' ),
        ),

        // Les ingrédients ont leur propre boîte (includes/ingredients.php),
        // et les étapes se rédigent dans l'éditeur principal de WordPress.

        // Case à cocher : si elle est cochée, la recette sera dans le slider
        // « recettes du jour ».
        'recette_du_jour' => array(
            'label' => 'Recette du jour',
            'type'  => 'checkbox',
        ),
    );
}

/**
 * Ajoute la boîte « Détails de la recette » sur l'écran d'édition.
 */
function resto_ajouter_boite_champs() {
    add_meta_box(
        'resto_recette_champs',            // identifiant interne de la boîte
        'Détails de la recette',           // titre affiché
        'resto_afficher_boite_champs',     // fonction qui dessine le contenu
        'recette',                         // sur quel type de contenu
        'normal',                          // emplacement (sous l'éditeur)
        'high'                             // priorité : tout en haut
    );
}
add_action( 'add_meta_boxes', 'resto_ajouter_boite_champs' );

/**
 * Dessine le formulaire : une ligne par champ de la liste ci-dessus.
 */
function resto_afficher_boite_champs( $post ) {

    // Jeton de sécurité : vérifié à l'enregistrement pour être sûr que
    // le formulaire vient bien de cette page.
    wp_nonce_field( 'resto_sauver_recette', 'resto_recette_nonce' );

    foreach ( resto_champs_recette() as $cle => $champ ) {

        // On relit la valeur déjà enregistrée. Le « _ » devant le nom cache
        // le champ de la liste générique « Champs personnalisés » de WordPress.
        $valeur = get_post_meta( $post->ID, '_' . $cle, true );

        echo '<p><label for="' . esc_attr( $cle ) . '"><strong>'
            . esc_html( $champ['label'] ) . '</strong></label><br>';

        switch ( $champ['type'] ) {

            case 'number':
                echo '<input type="number" min="0" id="' . esc_attr( $cle )
                    . '" name="' . esc_attr( $cle )
                    . '" value="' . esc_attr( $valeur ) . '">';
                break;

            case 'select':
                echo '<select id="' . esc_attr( $cle ) . '" name="' . esc_attr( $cle ) . '">';
                foreach ( $champ['options'] as $option ) {
                    echo '<option value="' . esc_attr( $option ) . '"'
                        . selected( $valeur, $option, false ) . '>'
                        . esc_html( $option ) . '</option>';
                }
                echo '</select>';
                break;

            case 'textarea':
                echo '<textarea rows="6" style="width:100%" id="' . esc_attr( $cle )
                    . '" name="' . esc_attr( $cle ) . '">'
                    . esc_textarea( $valeur ) . '</textarea>';
                break;

            case 'checkbox':
                echo '<input type="checkbox" id="' . esc_attr( $cle )
                    . '" name="' . esc_attr( $cle ) . '" value="1"'
                    . checked( $valeur, '1', false ) . '> Oui';
                break;
        }

        echo '</p>';
    }
}

/**
 * Enregistre les valeurs quand on clique sur « Mettre à jour ».
 */
function resto_sauver_champs_recette( $post_id ) {

    // 1. Sécurité : on s'arrête si le jeton est absent ou faux...
    if ( ! isset( $_POST['resto_recette_nonce'] )
        || ! wp_verify_nonce( $_POST['resto_recette_nonce'], 'resto_sauver_recette' ) ) {
        return;
    }
    // ...si c'est un enregistrement automatique en arrière-plan...
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    // ...ou si la personne n'a pas le droit de modifier cette recette.
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    // 2. On nettoie chaque valeur selon son type, puis on l'enregistre.
    foreach ( resto_champs_recette() as $cle => $champ ) {

        switch ( $champ['type'] ) {

            case 'number':
                $brut   = isset( $_POST[ $cle ] ) ? wp_unslash( $_POST[ $cle ] ) : '';
                $valeur = ( $brut === '' ) ? '' : absint( $brut );   // entier positif
                break;

            case 'select':
                $brut   = isset( $_POST[ $cle ] ) ? wp_unslash( $_POST[ $cle ] ) : '';
                $valeur = in_array( $brut, $champ['options'], true ) ? $brut : '';
                break;

            case 'textarea':
                $valeur = isset( $_POST[ $cle ] )
                    ? sanitize_textarea_field( wp_unslash( $_POST[ $cle ] ) )
                    : '';
                break;

            case 'checkbox':
                $valeur = isset( $_POST[ $cle ] ) ? '1' : '0';
                break;

            default:
                $valeur = '';
        }

        update_post_meta( $post_id, '_' . $cle, $valeur );
    }
}
add_action( 'save_post_recette', 'resto_sauver_champs_recette' );