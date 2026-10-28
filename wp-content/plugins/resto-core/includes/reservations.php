<?php
// Protection de base : on arrête si le fichier est ouvert hors de WordPress.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Réservation de table (gratuite, sans paiement).
 *
 * Principe : le restaurant a un nombre de places PAR SERVICE (midi, soir).
 * Pour un jour et un service, on additionne les personnes déjà réservées
 * (sauf les réservations annulées). S'il reste assez de places pour le
 * nombre de personnes demandé, les créneaux du service sont proposés ;
 * sinon ils sont barrés.
 *
 * Contenu de ce fichier :
 *   1. les services et leurs créneaux
 *   2. le calcul des disponibilités
 *   3. le type de contenu « Réservation » (écran d'administration)
 *   4. les réglages (nombre de places)
 *   5. le shortcode [reservation] et son échange avec la page (AJAX)
 *   6. les e-mails de confirmation
 */

/* ==========================================================================
   1. Services et créneaux
   ========================================================================== */

// Au-delà de ce nombre de personnes, on demande d'appeler le restaurant.
define( 'RESTO_RESA_MAX_PERSONNES', 9 );

// Combien de jours à l'avance on peut réserver.
define( 'RESTO_RESA_JOURS_MAX', 90 );

/**
 * Les services et leurs créneaux (un créneau toutes les 30 minutes).
 * Modifiable avec le filtre « resto_services ».
 */
function resto_services() {
    return apply_filters( 'resto_services', array(
        'midi' => array(
            'label'    => 'Midi',
            'creneaux' => array( '12:00', '12:30', '13:00', '13:30', '14:00' ),
        ),
        'soir' => array(
            'label'    => 'Soir',
            'creneaux' => array( '19:00', '19:30', '20:00', '20:30', '21:00', '21:30', '22:00' ),
        ),
    ) );
}

/**
 * Nombre de places d'un service (réglage de l'administration, 40 par défaut).
 */
function resto_places_service( $service ) {
    return max( 0, (int) get_option( 'resto_places_' . $service, 40 ) );
}

/**
 * Le service (midi / soir) auquel appartient une heure, ou '' si inconnue.
 */
function resto_service_de_heure( $heure ) {
    foreach ( resto_services() as $cle => $service ) {
        if ( in_array( $heure, $service['creneaux'], true ) ) {
            return $cle;
        }
    }
    return '';
}

/* ==========================================================================
   2. Disponibilités
   ========================================================================== */

/**
 * Une date est-elle réservable ? (format AAAA-MM-JJ, pas dans le passé,
 * dans les 90 jours, restaurant ouvert : fermé le lundi.)
 * Renvoie '' si oui, sinon la raison.
 */
function resto_date_probleme( $date ) {

    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
        return 'Choisissez une date.';
    }

    $jour = date_create_immutable( $date, wp_timezone() );
    if ( ! $jour || $jour->format( 'Y-m-d' ) !== $date ) {
        return 'Cette date n\'est pas valide.';
    }

    $aujourdhui = new DateTimeImmutable( 'today', wp_timezone() );
    if ( $jour < $aujourdhui ) {
        return 'Cette date est passée.';
    }
    if ( $jour > $aujourdhui->modify( '+' . RESTO_RESA_JOURS_MAX . ' days' ) ) {
        return 'Les réservations ouvrent ' . RESTO_RESA_JOURS_MAX . ' jours à l\'avance.';
    }
    if ( '1' === $jour->format( 'N' ) ) {
        return 'Le restaurant est fermé le lundi.';
    }

    return '';
}

/**
 * Nombre de personnes déjà réservées pour un jour et un service.
 */
function resto_personnes_reservees( $date, $service ) {

    $ids = get_posts( array(
        'post_type'      => 'reservation',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => array(
            'relation' => 'AND',
            array( 'key' => '_date', 'value' => $date ),
            array( 'key' => '_service', 'value' => $service ),
            array( 'key' => '_statut', 'value' => 'annulee', 'compare' => '!=' ),
        ),
    ) );

    $total = 0;
    foreach ( $ids as $id ) {
        $total += (int) get_post_meta( $id, '_personnes', true );
    }
    return $total;
}

/**
 * Le tableau des créneaux d'un jour pour un nombre de personnes :
 *   array( 'midi' => array( 'label' => 'Midi', 'creneaux' => array(
 *       array( 'heure' => '12:00', 'dispo' => true ), ... ) ), ... )
 */
function resto_calculer_creneaux( $date, $personnes ) {

    $maintenant = new DateTimeImmutable( 'now', wp_timezone() );
    $est_aujourdhui = ( $maintenant->format( 'Y-m-d' ) === $date );

    $resultat = array();
    foreach ( resto_services() as $cle => $service ) {

        $restantes = resto_places_service( $cle ) - resto_personnes_reservees( $date, $cle );
        $assez     = $restantes >= $personnes;

        $liste = array();
        foreach ( $service['creneaux'] as $heure ) {
            $dispo = $assez;
            // Aujourd'hui, un créneau déjà commencé n'est plus proposé.
            if ( $dispo && $est_aujourdhui && $heure <= $maintenant->format( 'H:i' ) ) {
                $dispo = false;
            }
            $liste[] = array( 'heure' => $heure, 'dispo' => $dispo );
        }

        $resultat[ $cle ] = array( 'label' => $service['label'], 'creneaux' => $liste );
    }

    return $resultat;
}

/* ==========================================================================
   3. Type de contenu « Réservation »
   ========================================================================== */

function resto_declarer_reservations() {

    register_post_type( 'reservation', array(
        'labels' => array(
            'name'          => 'Réservations',
            'singular_name' => 'Réservation',
            'menu_name'     => 'Réservations',
            'all_items'     => 'Toutes les réservations',
            'edit_item'     => 'Modifier la réservation',
            'search_items'  => 'Rechercher une réservation',
            'not_found'     => 'Aucune réservation',
        ),
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => true,
        'menu_icon'    => 'dashicons-calendar-alt',
        'menu_position' => 22,
        'supports'     => array( 'title' ),
        // Les réservations arrivent par le formulaire du site : pas de bouton « Ajouter ».
        'capability_type' => 'post',
        'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
        'map_meta_cap'    => true,
    ) );
}
add_action( 'init', 'resto_declarer_reservations' );

/**
 * Boîte « Détails de la réservation » : on voit les informations et on peut
 * annuler (une réservation annulée libère ses places).
 */
function resto_boite_reservation() {
    add_meta_box( 'resto_resa_details', 'Détails de la réservation', 'resto_afficher_boite_reservation', 'reservation', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'resto_boite_reservation' );

function resto_afficher_boite_reservation( $post ) {

    wp_nonce_field( 'resto_sauver_resa', 'resto_resa_nonce' );

    $lignes = array(
        'Date'        => get_post_meta( $post->ID, '_date', true ),
        'Heure'       => get_post_meta( $post->ID, '_heure', true ),
        'Personnes'   => get_post_meta( $post->ID, '_personnes', true ),
        'Prénom'      => get_post_meta( $post->ID, '_prenom', true ),
        'Nom'         => get_post_meta( $post->ID, '_nom', true ),
        'E-mail'      => get_post_meta( $post->ID, '_email', true ),
        'Téléphone'   => get_post_meta( $post->ID, '_tel', true ),
        'Demande'     => get_post_meta( $post->ID, '_message', true ),
    );

    echo '<table class="form-table"><tbody>';
    foreach ( $lignes as $libelle => $valeur ) {
        echo '<tr><th>' . esc_html( $libelle ) . '</th><td>' . esc_html( $valeur ) . '</td></tr>';
    }

    $statut = get_post_meta( $post->ID, '_statut', true );
    echo '<tr><th><label for="resto_statut">Statut</label></th><td><select id="resto_statut" name="resto_statut">';
    echo '<option value="confirmee"' . selected( $statut, 'confirmee', false ) . '>Confirmée</option>';
    echo '<option value="annulee"' . selected( $statut, 'annulee', false ) . '>Annulée (libère les places)</option>';
    echo '</select></td></tr></tbody></table>';
}

function resto_sauver_reservation( $post_id ) {

    if ( ! isset( $_POST['resto_resa_nonce'] ) || ! wp_verify_nonce( $_POST['resto_resa_nonce'], 'resto_sauver_resa' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $statut = ( isset( $_POST['resto_statut'] ) && 'annulee' === $_POST['resto_statut'] ) ? 'annulee' : 'confirmee';
    update_post_meta( $post_id, '_statut', $statut );
}
add_action( 'save_post_reservation', 'resto_sauver_reservation' );

/**
 * Colonnes de la liste : Date, Heure, Personnes, Client, Statut.
 */
function resto_colonnes_reservations( $colonnes ) {
    return array(
        'cb'          => $colonnes['cb'],
        'title'       => 'Réservation',
        'resto_date'  => 'Date',
        'resto_heure' => 'Heure',
        'resto_pers'  => 'Personnes',
        'resto_tel'   => 'Contact',
        'resto_stat'  => 'Statut',
    );
}
add_filter( 'manage_reservation_posts_columns', 'resto_colonnes_reservations' );

function resto_remplir_colonnes_reservations( $colonne, $post_id ) {

    switch ( $colonne ) {
        case 'resto_date':
            $date = get_post_meta( $post_id, '_date', true );
            echo $date ? esc_html( wp_date( 'D j M Y', date_create_immutable( $date, wp_timezone() )->getTimestamp() ) ) : '—';
            break;
        case 'resto_heure':
            echo esc_html( get_post_meta( $post_id, '_heure', true ) );
            break;
        case 'resto_pers':
            echo esc_html( get_post_meta( $post_id, '_personnes', true ) );
            break;
        case 'resto_tel':
            echo esc_html( get_post_meta( $post_id, '_email', true ) ) . '<br>' . esc_html( get_post_meta( $post_id, '_tel', true ) );
            break;
        case 'resto_stat':
            echo ( 'annulee' === get_post_meta( $post_id, '_statut', true ) ) ? 'Annulée' : 'Confirmée';
            break;
    }
}
add_action( 'manage_reservation_posts_custom_column', 'resto_remplir_colonnes_reservations', 10, 2 );

/* ==========================================================================
   4. Réglages : nombre de places par service
   ========================================================================== */

function resto_menu_reglages_resa() {
    add_submenu_page(
        'edit.php?post_type=reservation',
        'Réglages des réservations',
        'Réglages',
        'manage_options',
        'resto-reglages-resa',
        'resto_page_reglages_resa'
    );
}
add_action( 'admin_menu', 'resto_menu_reglages_resa' );

function resto_enregistrer_reglages_resa() {
    foreach ( array_keys( resto_services() ) as $cle ) {
        register_setting( 'resto_resa', 'resto_places_' . $cle, array(
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 40,
        ) );
    }
}
add_action( 'admin_init', 'resto_enregistrer_reglages_resa' );

function resto_page_reglages_resa() {
    echo '<div class="wrap"><h1>Réglages des réservations</h1>';
    echo '<p>Nombre de couverts que le restaurant peut accueillir à chaque service. '
        . 'Quand les réservations d\'un service atteignent ce nombre, ses créneaux sont barrés.</p>';
    echo '<form method="post" action="options.php">';
    settings_fields( 'resto_resa' );
    echo '<table class="form-table"><tbody>';
    foreach ( resto_services() as $cle => $service ) {
        echo '<tr><th><label for="resto_places_' . esc_attr( $cle ) . '">Places — ' . esc_html( $service['label'] ) . '</label></th>'
            . '<td><input type="number" min="0" id="resto_places_' . esc_attr( $cle ) . '" name="resto_places_' . esc_attr( $cle )
            . '" value="' . esc_attr( resto_places_service( $cle ) ) . '" class="small-text"></td></tr>';
    }
    echo '</tbody></table>';
    submit_button();
    echo '</form></div>';
}

/* ==========================================================================
   5. Shortcode [reservation] et échanges avec la page (AJAX)
   ========================================================================== */

/**
 * [reservation] : le formulaire. Les créneaux se chargent seuls quand le
 * visiteur choisit une date ou un nombre de personnes (assets/reservation.js).
 */
function resto_shortcode_reservation() {

    wp_enqueue_script(
        'resto-reservation',
        plugins_url( 'assets/reservation.js', dirname( __DIR__ ) . '/resto-core.php' ),
        array(),
        '1.0',
        true
    );
    wp_localize_script( 'resto-reservation', 'restoResa', array(
        'ajax'  => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'resto_resa' ),
    ) );

    $aujourdhui = wp_date( 'Y-m-d' );
    $maximum    = wp_date( 'Y-m-d', strtotime( '+' . RESTO_RESA_JOURS_MAX . ' days', current_time( 'timestamp' ) ) );

    ob_start();
    ?>
    <form class="resto-resa" novalidate>

        <div class="resto-resa-bloc">
            <span class="resto-resa-label">1 · Nombre de personnes</span>
            <div class="resto-resa-chips">
                <?php for ( $i = 1; $i <= RESTO_RESA_MAX_PERSONNES; $i++ ) : ?>
                    <label class="resto-chip"><input type="radio" name="personnes" value="<?php echo (int) $i; ?>"<?php checked( $i, 2 ); ?>><span><?php echo (int) $i; ?></span></label>
                <?php endfor; ?>
            </div>
            <p class="resto-resa-note">Plus de <?php echo (int) RESTO_RESA_MAX_PERSONNES; ?> personnes ? Appelez-nous.</p>
        </div>

        <div class="resto-resa-bloc">
            <label class="resto-resa-label" for="resa-date">Date</label>
            <input type="date" id="resa-date" name="date" min="<?php echo esc_attr( $aujourdhui ); ?>" max="<?php echo esc_attr( $maximum ); ?>" required>
        </div>

        <div class="resto-resa-bloc">
            <span class="resto-resa-label">2 · Horaire</span>
            <div class="resto-resa-creneaux" aria-live="polite">
                <p class="resto-resa-note">Choisissez une date pour voir les horaires.</p>
            </div>
        </div>

        <div class="resto-resa-bloc">
            <span class="resto-resa-label">3 · Vos coordonnées</span>
            <div class="resto-resa-grille">
                <label>Prénom<input type="text" name="prenom" autocomplete="given-name" required></label>
                <label>Nom<input type="text" name="nom" autocomplete="family-name" required></label>
                <label>E-mail<input type="email" name="email" autocomplete="email" required></label>
                <label>Téléphone<input type="tel" name="tel" autocomplete="tel" required></label>
                <label class="resto-resa-large">Une demande particulière ? (facultatif)<textarea name="message" rows="3" placeholder="Allergies, chaise haute, anniversaire…"></textarea></label>
            </div>
        </div>

        <!-- Piège à robots : les vrais visiteurs ne voient pas ce champ. -->
        <div class="resto-resa-piege" aria-hidden="true"><label>Site web<input type="text" name="site_web" tabindex="-1" autocomplete="off"></label></div>

        <div class="resto-resa-actions">
            <button type="submit" class="resto-resa-bouton">Réserver ma table</button>
            <span class="resto-resa-note">Gratuit, aucun paiement demandé.</span>
        </div>

        <div class="resto-resa-message" role="status" aria-live="polite"></div>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode( 'reservation', 'resto_shortcode_reservation' );

/**
 * AJAX 1 : les créneaux d'un jour (GET : date, personnes).
 */
function resto_ajax_creneaux() {

    $date      = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : '';
    $personnes = isset( $_GET['personnes'] ) ? absint( $_GET['personnes'] ) : 0;

    $probleme = resto_date_probleme( $date );
    if ( '' !== $probleme ) {
        wp_send_json_error( array( 'message' => $probleme ) );
    }
    if ( $personnes < 1 || $personnes > RESTO_RESA_MAX_PERSONNES ) {
        wp_send_json_error( array( 'message' => 'Choisissez le nombre de personnes.' ) );
    }

    wp_send_json_success( array( 'services' => resto_calculer_creneaux( $date, $personnes ) ) );
}
add_action( 'wp_ajax_resto_creneaux', 'resto_ajax_creneaux' );
add_action( 'wp_ajax_nopriv_resto_creneaux', 'resto_ajax_creneaux' );

/**
 * AJAX 2 : enregistre la réservation (POST). On refait TOUS les contrôles
 * côté serveur : la page peut être périmée (dernière place prise entre-temps).
 */
function resto_ajax_reserver() {

    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'resto_resa' ) ) {
        wp_send_json_error( array( 'message' => 'La page a expiré, rechargez-la puis recommencez.' ) );
    }

    // Piège à robots rempli : on fait semblant que tout va bien.
    if ( ! empty( $_POST['site_web'] ) ) {
        wp_send_json_success( array( 'message' => 'Merci !' ) );
    }

    $date      = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
    $heure     = isset( $_POST['heure'] ) ? sanitize_text_field( wp_unslash( $_POST['heure'] ) ) : '';
    $personnes = isset( $_POST['personnes'] ) ? absint( $_POST['personnes'] ) : 0;
    $prenom    = isset( $_POST['prenom'] ) ? sanitize_text_field( wp_unslash( $_POST['prenom'] ) ) : '';
    $nom       = isset( $_POST['nom'] ) ? sanitize_text_field( wp_unslash( $_POST['nom'] ) ) : '';
    $email     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
    $tel       = isset( $_POST['tel'] ) ? sanitize_text_field( wp_unslash( $_POST['tel'] ) ) : '';
    $message   = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

    $probleme = resto_date_probleme( $date );
    if ( '' !== $probleme ) {
        wp_send_json_error( array( 'message' => $probleme ) );
    }
    if ( $personnes < 1 || $personnes > RESTO_RESA_MAX_PERSONNES ) {
        wp_send_json_error( array( 'message' => 'Choisissez le nombre de personnes.' ) );
    }

    $service = resto_service_de_heure( $heure );
    if ( '' === $service ) {
        wp_send_json_error( array( 'message' => 'Choisissez un horaire.' ) );
    }
    if ( '' === $prenom || '' === $nom || '' === $tel ) {
        wp_send_json_error( array( 'message' => 'Merci de remplir votre prénom, votre nom et votre téléphone.' ) );
    }
    if ( ! is_email( $email ) ) {
        wp_send_json_error( array( 'message' => 'Cette adresse e-mail ne semble pas valide.' ) );
    }

    // Dernier contrôle des places (le créneau est-il toujours libre ?).
    $creneaux = resto_calculer_creneaux( $date, $personnes );
    $libre    = false;
    foreach ( $creneaux[ $service ]['creneaux'] as $creneau ) {
        if ( $creneau['heure'] === $heure && $creneau['dispo'] ) {
            $libre = true;
        }
    }
    if ( ! $libre ) {
        wp_send_json_error( array(
            'message' => 'Ce créneau vient d\'être pris, choisissez-en un autre.',
            'refresh' => true,
        ) );
    }

    // Enregistrement.
    $jour  = date_create_immutable( $date, wp_timezone() );
    $titre = $nom . ' ' . $prenom . ' — ' . $jour->format( 'd/m' ) . ' ' . $heure . ' (' . $personnes . ' pers.)';

    $id = wp_insert_post( array(
        'post_type'   => 'reservation',
        'post_status' => 'publish',
        'post_title'  => $titre,
    ), true );

    if ( is_wp_error( $id ) ) {
        wp_send_json_error( array( 'message' => 'Une erreur est survenue, réessayez dans un instant.' ) );
    }

    update_post_meta( $id, '_date', $date );
    update_post_meta( $id, '_heure', $heure );
    update_post_meta( $id, '_service', $service );
    update_post_meta( $id, '_personnes', $personnes );
    update_post_meta( $id, '_prenom', $prenom );
    update_post_meta( $id, '_nom', $nom );
    update_post_meta( $id, '_email', $email );
    update_post_meta( $id, '_tel', $tel );
    update_post_meta( $id, '_message', $message );
    update_post_meta( $id, '_statut', 'confirmee' );

    resto_envoyer_mails_reservation( $id );

    $jour_lisible = wp_date( 'l j F Y', $jour->getTimestamp() );

    wp_send_json_success( array(
        'message' => 'Merci ' . $prenom . ', votre table est réservée !',
        'detail'  => ucfirst( $jour_lisible ) . ' à ' . $heure . ', pour ' . $personnes . ' personne' . ( $personnes > 1 ? 's' : '' )
                   . '. Un e-mail de confirmation vient de vous être envoyé.',
    ) );
}
add_action( 'wp_ajax_resto_reserver', 'resto_ajax_reserver' );
add_action( 'wp_ajax_nopriv_resto_reserver', 'resto_ajax_reserver' );

/* ==========================================================================
   6. E-mails (envoyés vers Mailpit en local, voir mail.php)
   ========================================================================== */

function resto_envoyer_mails_reservation( $id ) {

    $prenom    = get_post_meta( $id, '_prenom', true );
    $nom       = get_post_meta( $id, '_nom', true );
    $email     = get_post_meta( $id, '_email', true );
    $tel       = get_post_meta( $id, '_tel', true );
    $message   = get_post_meta( $id, '_message', true );
    $heure     = get_post_meta( $id, '_heure', true );
    $personnes = (int) get_post_meta( $id, '_personnes', true );
    $date      = get_post_meta( $id, '_date', true );
    $jour      = ucfirst( wp_date( 'l j F Y', date_create_immutable( $date, wp_timezone() )->getTimestamp() ) );
    $site      = get_bloginfo( 'name' );

    $entetes = array( 'Content-Type: text/plain; charset=UTF-8' );

    // 1. Le client.
    $texte  = "Bonjour $prenom,\n\n";
    $texte .= "Votre table est réservée chez $site.\n\n";
    $texte .= "Date : $jour\nHeure : $heure\nPersonnes : $personnes\n\n";
    $texte .= "Nous gardons votre table 15 minutes après l'heure prévue.\n";
    $texte .= "Un empêchement ? Écrivez-nous depuis la page Contact : " . home_url( '/contact/' ) . "\n\n";
    $texte .= "À très bientôt !\n$site\n";
    wp_mail( $email, 'Votre réservation chez ' . $site . ' — ' . $jour . ' à ' . $heure, $texte, $entetes );

    // 2. Le restaurant.
    $texte  = "Nouvelle réservation\n\n";
    $texte .= "$jour à $heure — $personnes personne(s)\n";
    $texte .= "Client : $prenom $nom\nE-mail : $email\nTéléphone : $tel\n";
    if ( '' !== $message ) {
        $texte .= "Demande : $message\n";
    }
    $texte .= "\nVoir : " . admin_url( 'post.php?post=' . $id . '&action=edit' ) . "\n";
    wp_mail( get_option( 'admin_email' ), 'Nouvelle réservation — ' . $jour . ' ' . $heure . ' (' . $personnes . ' pers.)', $texte, $entetes );
}

/* ==========================================================================
   7. Page de réservation : classe CSS pour adapter la page
   ========================================================================== */

/**
 * Ajoute la classe « page-reservation » au <body> de la page qui contient le
 * shortcode [reservation]. Le thème s'en sert pour cacher le bandeau orange
 * « Réservez votre table » du footer : on est déjà sur la page de réservation.
 */
function resto_classe_page_reservation( $classes ) {

    if ( is_singular() && has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), 'reservation' ) ) {
        $classes[] = 'page-reservation';
    }
    return $classes;
}
add_filter( 'body_class', 'resto_classe_page_reservation' );