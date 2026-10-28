<?php
// Protection de base : on arrête si le fichier est ouvert hors de WordPress.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Envoie tous les e-mails du site vers Mailpit (la boîte de test de Docker).
 *
 * Sans ce réglage, WordPress essaie d'envoyer par le serveur local, qui
 * n'existe pas dans le conteneur : les mails (réservations, formulaire de
 * contact, commandes) ne partiraient jamais. Avec lui, tout arrive dans
 * Mailpit : http://localhost:8025
 *
 * Réglages possibles (variables d'environnement du conteneur WordPress) :
 *   RESTO_SMTP_HOST  nom du service Mailpit dans docker-compose (défaut : mailpit)
 *   RESTO_SMTP_PORT  port SMTP de Mailpit (défaut : 1025)
 */
function resto_mails_vers_mailpit( $phpmailer ) {

    $hote = getenv( 'RESTO_SMTP_HOST' );
    $port = getenv( 'RESTO_SMTP_PORT' );

    $phpmailer->isSMTP();
    $phpmailer->Host        = $hote ? $hote : 'mailpit';
    $phpmailer->Port        = $port ? (int) $port : 1025;
    $phpmailer->SMTPAuth    = false;
    $phpmailer->SMTPSecure  = '';
    $phpmailer->SMTPAutoTLS = false;
}
add_action( 'phpmailer_init', 'resto_mails_vers_mailpit' );

// Adresse et nom de l'expéditeur : « Sigma <reservations@sigma.test> ».
add_filter( 'wp_mail_from', function () {
    return 'reservations@sigma.test';
} );
add_filter( 'wp_mail_from_name', function () {
    return get_bloginfo( 'name' );
} );