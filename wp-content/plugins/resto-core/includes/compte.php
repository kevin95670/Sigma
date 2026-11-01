<?php
/**
 * Icône « Mon compte » à côté du panier, dans le header.
 * Kadence gratuit n'a pas d'élément « compte » : on l'ajoute avec un petit script.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function resto_icone_compte() {
	if ( ! function_exists( 'wc_get_page_permalink' ) ) {
		return;
	}
	$url = esc_url( wc_get_page_permalink( 'myaccount' ) );
	?>
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		var lien = '<a class="sigma-compte" href="<?php echo $url; ?>" aria-label="Mon compte">'
			+ '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg></a>';
		// Un panier dans le header desktop, un autre dans le header mobile :
		// on cherche toute classe qui contient « cart-wrap » dans l'en-tête
		document.querySelectorAll('#masthead [class*="cart-wrap"], #mobile-header [class*="cart-wrap"]').forEach(function (panier) {
			// on évite de l'ajouter deux fois (et dans un élément déjà à l'intérieur d'un panier)
			if (panier.parentElement.closest('[class*="cart-wrap"]')) { return; }
			if (panier.previousElementSibling && panier.previousElementSibling.classList.contains('sigma-compte')) { return; }
			panier.insertAdjacentHTML('beforebegin', lien);
		});
	});
	</script>
	<?php
}
add_action( 'wp_footer', 'resto_icone_compte' );