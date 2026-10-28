/**
 * Boîte « Ingrédients » : ajouter / supprimer des lignes,
 * choisir / retirer une image depuis la médiathèque WordPress.
 */
jQuery(function ($) {

    var $lignes = $('#resto-ingredients-lignes');
    var modele  = $('#resto-ingredient-modele').html();   // la ligne vide à copier
    var compteur = $lignes.children('tr').length;         // numéro de la prochaine ligne

    // Ajouter une ligne.
    $('#resto-ajouter-ingredient').on('click', function () {
        $lignes.append(modele.replace(/__INDEX__/g, compteur));
        compteur++;
    });

    // Supprimer une ligne.
    $lignes.on('click', '.resto-supprimer-ligne', function () {
        $(this).closest('tr').remove();
    });

    // Choisir une image : ouvre la médiathèque WordPress.
    $lignes.on('click', '.resto-choisir-image', function (e) {
        e.preventDefault();

        var $ligne = $(this).closest('tr');

        var cadre = wp.media({
            title: 'Choisir une image',
            button: { text: 'Utiliser cette image' },
            library: { type: 'image' },
            multiple: false
        });

        cadre.on('select', function () {
            var image = cadre.state().get('selection').first().toJSON();
            var url = (image.sizes && image.sizes.thumbnail) ? image.sizes.thumbnail.url : image.url;

            $ligne.find('.resto-ingredient-image-id').val(image.id);
            $ligne.find('.resto-ingredient-apercu').html('<img src="' + url + '" alt="">');
            $ligne.find('.resto-retirer-image').show();
        });

        cadre.open();
    });

    // Retirer l'image d'une ligne.
    $lignes.on('click', '.resto-retirer-image', function () {
        var $ligne = $(this).closest('tr');
        $ligne.find('.resto-ingredient-image-id').val('');
        $ligne.find('.resto-ingredient-apercu').empty();
        $(this).hide();
    });
});