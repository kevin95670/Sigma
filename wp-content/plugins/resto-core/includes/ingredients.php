<?php
// Protection de base : on arrête si le fichier est ouvert hors de WordPress.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ajoute la boîte « Ingrédients » sur l'écran d'édition d'une recette.
 */
function resto_ajouter_boite_ingredients() {
    add_meta_box(
        'resto_recette_ingredients',        // identifiant interne
        'Ingrédients',                      // titre affiché
        'resto_afficher_boite_ingredients', // fonction qui dessine le contenu
        'recette',                          // sur quel type de contenu
        'normal',                           // emplacement
        'high'                              // priorité
    );
}
add_action( 'add_meta_boxes', 'resto_ajouter_boite_ingredients' );

/**
 * Dessine UNE ligne du tableau (image, quantité, nom, bouton supprimer).
 * Sert à la fois pour les lignes déjà enregistrées et pour le « modèle »
 * que le JavaScript copie quand on clique sur « Ajouter un ingrédient ».
 */
function resto_ligne_ingredient( $index, $ingredient = array() ) {

    $nom      = isset( $ingredient['nom'] ) ? $ingredient['nom'] : '';
    $quantite = isset( $ingredient['quantite'] ) ? $ingredient['quantite'] : '';
    $image_id = isset( $ingredient['image'] ) ? absint( $ingredient['image'] ) : 0;

    // Petite miniature de l'image choisie (vide s'il n'y en a pas).
    $apercu = $image_id ? wp_get_attachment_image( $image_id, 'thumbnail' ) : '';
    ?>
    <tr class="resto-ingredient">
        <td class="resto-ingredient-image">
            <span class="resto-ingredient-apercu"><?php echo $apercu; // phpcs:ignore ?></span>
            <input type="hidden" class="resto-ingredient-image-id"
                   name="resto_ingredients[<?php echo esc_attr( $index ); ?>][image]"
                   value="<?php echo $image_id ? esc_attr( $image_id ) : ''; ?>">
            <button type="button" class="button resto-choisir-image">Choisir une image</button>
            <button type="button" class="button-link resto-retirer-image"
                    <?php echo $image_id ? '' : 'style="display:none"'; ?>>Retirer</button>
        </td>
        <td>
            <input type="text" class="regular-text"
                   name="resto_ingredients[<?php echo esc_attr( $index ); ?>][quantite]"
                   value="<?php echo esc_attr( $quantite ); ?>"
                   placeholder="Quantité (ex. 200 g)">
        </td>
        <td>
            <input type="text" class="regular-text"
                   name="resto_ingredients[<?php echo esc_attr( $index ); ?>][nom]"
                   value="<?php echo esc_attr( $nom ); ?>"
                   placeholder="Ingrédient (ex. Farine)">
        </td>
        <td>
            <button type="button" class="button-link button-link-delete resto-supprimer-ligne">Supprimer</button>
        </td>
    </tr>
    <?php
}

/**
 * Dessine toute la boîte : le tableau, le bouton d'ajout et le modèle de ligne.
 */
function resto_afficher_boite_ingredients( $post ) {

    // Jeton de sécurité, vérifié à l'enregistrement.
    wp_nonce_field( 'resto_sauver_ingredients', 'resto_ingredients_nonce' );

    // On relit la liste enregistrée (un tableau de lignes).
    $ingredients = get_post_meta( $post->ID, '_ingredients', true );

    // S'il n'y en a pas encore, on affiche une ligne vide pour commencer.
    if ( ! is_array( $ingredients ) || empty( $ingredients ) ) {
        $ingredients = array( array() );
    }
    ?>
    <style>
        .resto-ingredient-apercu img { width: 60px; height: 60px; object-fit: cover; display: block; margin-bottom: 6px; }
        .resto-ingredients td { vertical-align: middle; }
    </style>

    <table class="widefat resto-ingredients">
        <thead>
            <tr>
                <th>Image</th>
                <th>Quantité</th>
                <th>Ingrédient</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="resto-ingredients-lignes">
            <?php
            foreach ( array_values( $ingredients ) as $i => $ingredient ) {
                resto_ligne_ingredient( $i, $ingredient );
            }
            ?>
        </tbody>
    </table>

    <p>
        <button type="button" class="button button-primary" id="resto-ajouter-ingredient">
            Ajouter un ingrédient
        </button>
    </p>

    <?php
    // Modèle d'une ligne vide, copié par le JavaScript (« __INDEX__ » est
    // remplacé par le numéro de la nouvelle ligne).
    ?>
    <script type="text/template" id="resto-ingredient-modele">
        <?php resto_ligne_ingredient( '__INDEX__' ); ?>
    </script>
    <?php
}

/**
 * Charge le JavaScript (et la médiathèque WordPress) uniquement sur
 * l'écran d'édition d'une recette.
 */
function resto_charger_scripts_ingredients( $hook ) {

    global $post;

    if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true )
        || ! $post || 'recette' !== $post->post_type ) {
        return;
    }

    // Charge la fenêtre « Médiathèque » de WordPress (choix d'une image).
    wp_enqueue_media();

    $fichier = RESTO_CORE_PATH . 'assets/ingredients.js';

    wp_enqueue_script(
        'resto-ingredients',
        plugins_url( 'assets/ingredients.js', RESTO_CORE_PATH . 'resto-core.php' ),
        array( 'jquery' ),
        file_exists( $fichier ) ? filemtime( $fichier ) : '0.1.0',   // change à chaque modification
        true
    );
}
add_action( 'admin_enqueue_scripts', 'resto_charger_scripts_ingredients' );

/**
 * Enregistre la liste d'ingrédients quand on clique sur « Mettre à jour ».
 */
function resto_sauver_ingredients( $post_id ) {

    // Mêmes vérifications de sécurité que pour les autres champs.
    if ( ! isset( $_POST['resto_ingredients_nonce'] )
        || ! wp_verify_nonce( $_POST['resto_ingredients_nonce'], 'resto_sauver_ingredients' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $propres = array();

    if ( isset( $_POST['resto_ingredients'] ) && is_array( $_POST['resto_ingredients'] ) ) {

        foreach ( wp_unslash( $_POST['resto_ingredients'] ) as $ingredient ) {

            $nom      = isset( $ingredient['nom'] ) ? sanitize_text_field( $ingredient['nom'] ) : '';
            $quantite = isset( $ingredient['quantite'] ) ? sanitize_text_field( $ingredient['quantite'] ) : '';
            $image    = isset( $ingredient['image'] ) ? absint( $ingredient['image'] ) : 0;

            // On ignore les lignes complètement vides.
            if ( '' === $nom && '' === $quantite && ! $image ) {
                continue;
            }

            $propres[] = array(
                'nom'      => $nom,
                'quantite' => $quantite,
                'image'    => $image,
            );
        }
    }

    update_post_meta( $post_id, '_ingredients', $propres );
}
add_action( 'save_post_recette', 'resto_sauver_ingredients' );