<?php
// Si quelqu'un ouvre ce fichier directement dans le navigateur (hors WordPress),
// la constante ABSPATH n'existe pas : on arrête tout. C'est une protection de base.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Déclare le type de contenu « Recette » et la taxonomie « Type de recette ».
 */
function resto_register_recettes() {

    // register_post_type( identifiant, options ) : crée un nouveau type de contenu,
    // comme « Articles » ou « Pages », mais à nous.
    // L'identifiant 'recette' est interne : minuscules, sans espace, 20 caractères max.
    register_post_type( 'recette', array(

        // Les textes affichés dans l'administration.
        'labels' => array(
            'name'          => 'Recettes',                    // titre du menu (pluriel)
            'singular_name' => 'Recette',                     // nom au singulier
            'add_new'       => 'Ajouter une recette',         // sous-menu d'ajout
            'add_new_item'  => 'Ajouter une nouvelle recette',// titre de l'écran d'ajout
            'edit_item'     => 'Modifier la recette',         // titre de l'écran de modification
            'all_items'     => 'Toutes les recettes',         // sous-menu de la liste
            'search_items'  => 'Rechercher une recette',      // bouton de recherche
            'not_found'     => 'Aucune recette trouvée',      // message si la liste est vide
        ),

        // true = les recettes ont une adresse publique visible par les visiteurs.
        // Avec false, elles resteraient cachées (réservées à l'administration).
        'public'        => true,

        // false = WordPress ne crée pas de page de liste automatique (/recettes/).
        // On utilisera nous-mêmes une vraie page « Recettes », pour ne pas créer
        // deux pages à la même adresse.
        'has_archive'   => false,

        // Début de l'adresse d'une recette : monsite.fr/recette/quiche-lorraine/
        // Au singulier exprès, pour ne pas entrer en conflit avec la page « Recettes ».
        'rewrite'       => array( 'slug' => 'recette' ),

        // Icône du menu de gauche (ensemble d'icônes intégré à WordPress).
        'menu_icon'     => 'dashicons-food',

        // Position dans le menu : 5 = juste sous « Articles ».
        'menu_position' => 5,

        // true = rend le type accessible à l'éditeur de blocs moderne.
        // Sans ça, on aurait l'ancien éditeur.
        'show_in_rest'  => true,

        // Les éléments proposés à l'écran de modification :
        // titre, zone de texte principale, image mise en avant, résumé.
        'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
    ) );

    // register_taxonomy( identifiant, type de contenu concerné, options ) :
    // crée un système de classement, comme les « Catégories » pour les articles.
    register_taxonomy( 'type_recette', 'recette', array(

        // Les textes affichés dans l'administration.
        'labels' => array(
            'name'          => 'Types de recette',
            'singular_name' => 'Type de recette',
            'add_new_item'  => 'Ajouter un type',
        ),

        // true = fonctionne comme les catégories : cases à cocher, avec
        // possibilité de sous-catégories. false = fonctionne comme les étiquettes
        // (on tape des mots).
        'hierarchical'      => true,

        // true = chaque type a sa page publique (par exemple « recettes sucrées »).
        'public'            => true,

        // Début de l'adresse d'un type : monsite.fr/type-recette/sucree/
        'rewrite'           => array( 'slug' => 'type-recette' ),

        // true = disponible dans l'éditeur de blocs moderne.
        'show_in_rest'      => true,

        // true = ajoute une colonne « Type de recette » dans la liste des recettes
        // de l'administration, pratique pour s'y retrouver.
        'show_admin_column' => true,
    ) );
}

// add_action( moment, fonction ) : un « hook ».
// On dit à WordPress : « quand tu arrives à l'étape 'init' de ton chargement,
// lance ma fonction ». C'est ainsi qu'un plugin s'accroche à WordPress.
add_action( 'init', 'resto_register_recettes' );

/**
 * Crée les deux types de départ (Sucrée, Salée) s'ils n'existent pas déjà.
 */
function resto_creer_termes() {

    // Pour chaque paire « nom affiché => identifiant dans l'adresse ».
    foreach ( array( 'Sucrée' => 'sucree', 'Salée' => 'salee' ) as $nom => $slug ) {

        // term_exists : vérifie si ce type existe déjà. Sans ça, on le créerait
        // en double à chaque réactivation du plugin.
        if ( ! term_exists( $slug, 'type_recette' ) ) {

            // wp_insert_term : crée le type dans la base de données.
            wp_insert_term( $nom, 'type_recette', array( 'slug' => $slug ) );
        }
    }
}