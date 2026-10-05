<?php
/**
 * Page de recette : adresse virtuelle rendue à la volée par le thème actif.
 *
 * /?waw_pattern_review=<composition> affiche toutes les déclinaisons de la
 * composition, dans le gabarit du thème (en-tête et pied de page compris),
 * sans aucune page en base. Paramètres facultatifs :
 * - waw_pr_variant=<n>  une seule déclinaison (numérotée à partir de 1) ;
 * - waw_pr_chrome=0     sans en-tête ni pied de page ;
 * - waw_pr_embed=1      sans barre d'administration, hauteur transmise à la
 *                       fenêtre parente (aperçus de l'écran de recette).
 * Avec Polylang, la langue est celle de l'URL (pll_home_url()).
 *
 * Réservée aux personnes qui peuvent modifier l'apparence ; jamais indexée.
 *
 * @package WAW\PatternReview
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'query_vars', 'waw_pattern_review_query_vars' );
/**
 * Variables de requête de la page de recette.
 *
 * @param string[] $vars Variables publiques.
 * @return string[]
 */
function waw_pattern_review_query_vars( $vars ) {
	return array_merge( $vars, array( 'waw_pattern_review', 'waw_pr_variant', 'waw_pr_chrome', 'waw_pr_embed' ) );
}

/**
 * Composition demandée, si la page de recette est demandée et autorisée.
 *
 * @return array|null
 */
function waw_pattern_review_requested() {
	static $pattern = false;
	if ( false !== $pattern ) {
		return $pattern;
	}

	$slug    = (string) get_query_var( 'waw_pattern_review' );
	$pattern = null;
	if ( '' !== $slug && current_user_can( 'edit_theme_options' ) && wp_is_block_theme() ) {
		$pattern = waw_pattern_review_get_pattern( $slug );
	}
	return $pattern;
}

/**
 * URL de la page de recette.
 *
 * @param string $slug Nom de la composition.
 * @param array  $args Paramètres supplémentaires (waw_pr_*).
 * @param string $lang Langue Polylang ('' : langue par défaut).
 * @return string
 */
function waw_pattern_review_url( $slug, array $args = array(), $lang = '' ) {
	$base = function_exists( 'pll_home_url' ) && '' !== $lang ? pll_home_url( $lang ) : home_url( '/' );
	return add_query_arg( array_merge( array( 'waw_pattern_review' => $slug ), $args ), $base );
}

add_action( 'wp', 'waw_pattern_review_prepare' );
/**
 * Prépare la réponse : 404 si non autorisée, pas d'indexation ni de cache,
 * barre d'administration masquée en aperçu (avant son initialisation, qui a
 * lieu au début de template_redirect).
 */
function waw_pattern_review_prepare() {
	if ( '' === (string) get_query_var( 'waw_pattern_review' ) ) {
		return;
	}

	if ( ! waw_pattern_review_requested() ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		return;
	}

	nocache_headers();
	add_filter( 'wp_robots', 'wp_robots_no_robots' );

	if ( get_query_var( 'waw_pr_embed' ) ) {
		add_filter( 'show_admin_bar', '__return_false' );
		add_action( 'wp_footer', 'waw_pattern_review_height_script' );
	}
}

add_filter( 'template_include', 'waw_pattern_review_template', 100 );
/**
 * Rend la page de recette dans le canevas des thèmes de blocs.
 *
 * @param string $template Gabarit prévu.
 * @return string
 */
function waw_pattern_review_template( $template ) {
	$pattern = waw_pattern_review_requested();
	if ( ! $pattern ) {
		return $template;
	}

	global $_wp_current_template_id, $_wp_current_template_content;
	$_wp_current_template_id      = get_stylesheet() . '//waw-pattern-review';
	$_wp_current_template_content = waw_pattern_review_page_markup( $pattern );

	add_filter(
		'document_title_parts',
		static function ( $parts ) use ( $pattern ) {
			/* translators: %s: titre de la composition. */
			$parts['title'] = sprintf( __( 'Recette : %s', 'waw-pattern-review' ), waw_pattern_review_texts( $pattern )['title'] );
			return $parts;
		}
	);

	return ABSPATH . WPINC . '/template-canvas.php';
}

/**
 * Balisage de la page : en-tête et pied du thème, déclinaisons au centre.
 *
 * @param array $pattern Composition.
 * @return string
 */
function waw_pattern_review_page_markup( array $pattern ) {
	$texts    = waw_pattern_review_texts( $pattern );
	$variants = waw_pattern_review_build( $pattern );
	$total    = count( $variants );
	$only     = absint( get_query_var( 'waw_pr_variant' ) );
	$chrome   = '0' !== (string) get_query_var( 'waw_pr_chrome' );

	$parts = array();
	$parts[] = '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">' . esc_html( $texts['title'] ) . '</h1><!-- /wp:heading -->';

	$intro = sprintf(
		/* translators: 1: nom de la composition, 2: nombre de déclinaisons. */
		_n( 'Composition %1$s, %2$d déclinaison.', 'Composition %1$s, %2$d déclinaisons.', $total, 'waw-pattern-review' ),
		'<code>' . esc_html( $pattern['slug'] ) . '</code>',
		$total
	);
	$parts[] = '<!-- wp:paragraph --><p>' . $intro . '</p><!-- /wp:paragraph -->';
	if ( '' !== $texts['description'] ) {
		$parts[] = '<!-- wp:paragraph --><p>' . esc_html( $texts['description'] ) . '</p><!-- /wp:paragraph -->';
	}

	foreach ( $variants as $index => $variant ) {
		if ( $only && $only !== $index + 1 ) {
			continue;
		}
		$title = sprintf(
			/* translators: 1: numéro, 2: total, 3: libellé de la déclinaison. */
			__( 'Déclinaison %1$d/%2$d : %3$s', 'waw-pattern-review' ),
			$index + 1,
			$total,
			$variant['label']
		);
		$parts[] = '<!-- wp:spacer {"height":"4rem"} --><div style="height:4rem" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->';
		$parts[] = '<!-- wp:heading {"style":{"typography":{"fontSize":"1.25rem"}}} --><h2 class="wp-block-heading" id="waw-pr-variant-' . ( $index + 1 ) . '" style="font-size:1.25rem">' . esc_html( $title ) . '</h2><!-- /wp:heading -->';
		// Marge pour les compositions qui remontent sous le bloc précédent.
		$parts[] = '<!-- wp:spacer {"height":"2rem"} --><div style="height:2rem" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->';
		$parts[] = $variant['markup'];
	}

	$main = '<!-- wp:group {"tagName":"main","style":{"spacing":{"padding":{"top":"2rem","bottom":"4rem"}}},"layout":{"type":"constrained"}} --><main class="wp-block-group" style="padding-top:2rem;padding-bottom:4rem">'
		. implode( "\n\n", $parts )
		. '</main><!-- /wp:group -->';

	$header = '';
	$footer = '';
	if ( $chrome ) {
		if ( get_block_template( get_stylesheet() . '//header', 'wp_template_part' ) ) {
			$header = '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->';
		}
		if ( get_block_template( get_stylesheet() . '//footer', 'wp_template_part' ) ) {
			$footer = '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->';
		}
	}

	return $header . "\n\n" . $main . "\n\n" . $footer;
}

/**
 * Aperçu intégré : transmet la hauteur du document à l'écran de recette.
 */
function waw_pattern_review_height_script() {
	wp_print_inline_script_tag(
		"( () => {\n" .
		"\tconst send = () => window.parent.postMessage( { type: 'waw-pattern-review:height', height: document.documentElement.scrollHeight, src: location.href }, location.origin );\n" .
		"\tnew ResizeObserver( send ).observe( document.documentElement );\n" .
		"\twindow.addEventListener( 'load', send );\n" .
		'} )();'
	);
}
