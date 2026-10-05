<?php
/**
 * Compositions du thème et leurs déclinaisons.
 *
 * Une déclinaison = un style de la racine × une largeur de la racine :
 * - styles : « Par défaut », c'est-à-dire la composition telle qu'insérée
 *   (son style livré, comme l'extension WAW : sélecteur de styles l'entend),
 *   puis les autres styles autorisés pour elle
 *   (waw_style_picker_restrict_pattern_styles()). L'absence de style n'est
 *   déclinée que si c'est le « Par défaut ». Sans restriction déclarée, la
 *   composition n'a que sa version par défaut ;
 * - largeurs : Large et Pleine largeur quand le bloc racine les accepte et que
 *   l'alignement reste modifiable (pas de verrou contentOnly), sinon la largeur
 *   livrée.
 * Chaque axe se règle par un filtre (voir README).
 *
 * Les fichiers de composition sont inclus directement à chaque rendu : le
 * registre de WordPress garde le contenu de la première inclusion, dans la
 * langue de ce moment-là, et n'est jamais à jour d'une modification en cours.
 *
 * @package WAW\PatternReview
 */

defined( 'ABSPATH' ) || exit;

/**
 * Compositions du thème actif (et de son parent), par nom.
 *
 * @return array<string, array{slug: string, title: string, description: string, file: string, text_domain: string, categories: string[]}>
 */
function waw_pattern_review_patterns() {
	$themes = array_filter( array( wp_get_theme()->parent(), wp_get_theme() ) );

	/**
	 * Inclure les compositions masquées de l'outil d'insertion (Inserter: false),
	 * comme l'en-tête et le pied de page.
	 *
	 * @param bool $include Faux par défaut.
	 */
	$include_hidden = (bool) apply_filters( 'waw_pattern_review_include_hidden', false );

	$patterns = array();
	foreach ( $themes as $theme ) {
		$dir = $theme->get_stylesheet_directory() . '/patterns/';
		foreach ( $theme->get_block_patterns() as $file => $data ) {
			if ( empty( $data['slug'] ) || ( ! $include_hidden && false === ( $data['inserter'] ?? true ) ) ) {
				continue;
			}
			$patterns[ $data['slug'] ] = array(
				'slug'        => $data['slug'],
				'title'       => (string) ( $data['title'] ?? $data['slug'] ),
				'description' => (string) ( $data['description'] ?? '' ),
				'file'        => $dir . $file,
				'text_domain' => (string) $theme->get( 'TextDomain' ),
				'categories'  => (array) ( $data['categories'] ?? array() ),
			);
		}
	}

	ksort( $patterns );

	/**
	 * Compositions recettées.
	 *
	 * @param array $patterns Nom de composition => données.
	 */
	return apply_filters( 'waw_pattern_review_patterns', $patterns );
}

/**
 * Composition par son nom.
 *
 * @param string $slug Nom de la composition.
 * @return array|null
 */
function waw_pattern_review_get_pattern( $slug ) {
	$patterns = waw_pattern_review_patterns();
	return $patterns[ $slug ] ?? null;
}

/**
 * Titre et description traduits dans la langue courante.
 *
 * @param array $pattern Composition.
 * @return array{title: string, description: string}
 */
function waw_pattern_review_texts( array $pattern ) {
	$domain = $pattern['text_domain'];
	return array(
		'title'       => '' === $domain ? $pattern['title'] : translate_with_gettext_context( $pattern['title'], 'Pattern title', $domain ),
		'description' => '' === $domain || '' === $pattern['description'] ? $pattern['description'] : translate_with_gettext_context( $pattern['description'], 'Pattern description', $domain ),
	);
}

/**
 * Blocs de la composition, rendus dans la langue courante.
 *
 * @param array $pattern Composition.
 * @return array[] Blocs de premier niveau (parse_blocks()), sans les blocs vides.
 */
function waw_pattern_review_blocks( array $pattern ) {
	if ( ! is_readable( $pattern['file'] ) ) {
		return array();
	}

	// Portée isolée : les variables du fichier ne touchent pas celles d'ici.
	ob_start();
	( static function () {
		include func_get_arg( 0 );
	} )( $pattern['file'] );
	$markup = (string) ob_get_clean();

	return array_values( array_filter( parse_blocks( $markup ), static fn( $block ) => ! empty( $block['blockName'] ) ) );
}

/**
 * Style is-style-* d'une liste de classes ('' si aucun).
 *
 * @param string $class_name Classes.
 * @return string
 */
function waw_pattern_review_style_of( $class_name ) {
	return preg_match( '/(?:^|\s)is-style-([^\s]+)/', (string) $class_name, $m ) ? $m[1] : '';
}

/**
 * Déclinaisons d'une composition.
 *
 * @param array $pattern Composition.
 * @param array $root    Bloc racine.
 * @return array<int, array{style: string, align: string, default: bool, delivered: bool}>
 *   default : style « Par défaut » (la configuration de base) ;
 *   delivered : composition telle qu'insérée (style et largeur par défaut).
 */
function waw_pattern_review_variants( array $pattern, array $root ) {
	$delivered_style = waw_pattern_review_style_of( $root['attrs']['className'] ?? '' );
	$delivered_align = (string) ( $root['attrs']['align'] ?? '' );

	// Styles : restriction déclarée dans l'extension WAW : sélecteur de styles,
	// dont le « Par défaut » est le style livré (ou celui imposé par l'option
	// 'default' de la restriction).
	$styles = array( $delivered_style );
	if ( function_exists( 'waw_style_picker_get_config' ) ) {
		$config  = waw_style_picker_get_config();
		$allowed = $config['patternStyles'][ $pattern['slug'] ] ?? null;
		if ( null !== $allowed ) {
			$default = (string) ( $config['patternDefaults'][ $pattern['slug'] ] ?? $delivered_style );
			$styles  = array_merge( array( $default ), (array) $allowed );
		}
	}

	/**
	 * Styles de la racine à recetter ('' : « Par défaut »).
	 *
	 * @param string[] $styles  Noms de styles, le style livré en premier.
	 * @param array    $pattern Composition.
	 * @param array    $root    Bloc racine.
	 */
	$styles = array_values( array_unique( (array) apply_filters( 'waw_pattern_review_styles', $styles, $pattern, $root ) ) );

	// Largeurs : Large et Pleine largeur si le bloc les accepte et qu'elles
	// restent modifiables (une racine contentOnly masque l'alignement).
	$aligns   = array( $delivered_align );
	$type     = WP_Block_Type_Registry::get_instance()->get_registered( $root['blockName'] );
	$supports = $type->supports['align'] ?? false;
	$both     = true === $supports || ( is_array( $supports ) && in_array( 'wide', $supports, true ) && in_array( 'full', $supports, true ) );
	$locked   = 'contentOnly' === ( $root['attrs']['templateLock'] ?? '' );
	if ( $both && ! $locked && in_array( $delivered_align, array( 'wide', 'full' ), true ) ) {
		$aligns = array( $delivered_align, 'wide', 'full' );
	}

	/**
	 * Largeurs de la racine à recetter ('' : largeur du contenu).
	 *
	 * @param string[] $aligns  Alignements, la largeur livrée en premier.
	 * @param array    $pattern Composition.
	 * @param array    $root    Bloc racine.
	 */
	$aligns = array_values( array_unique( (array) apply_filters( 'waw_pattern_review_alignments', $aligns, $pattern, $root ) ) );

	$variants = array();
	foreach ( $styles as $style ) {
		foreach ( $aligns as $align ) {
			$variants[] = array(
				'style'     => (string) $style,
				'align'     => (string) $align,
				'default'   => $style === $styles[0],
				'delivered' => $style === $styles[0] && $align === $delivered_align,
			);
		}
	}

	return $variants;
}

/**
 * Remplace sur place (ou ajoute en fin) une classe dans une liste : l'ordre
 * reste celui que produit save(), sinon l'éditeur réécrirait le bloc.
 *
 * @param string[] $classes   Classes.
 * @param callable $is_target Reconnaît la classe à remplacer.
 * @param string   $new_class Nouvelle classe ('' : retirer).
 * @return string[]
 */
function waw_pattern_review_replace_class( array $classes, callable $is_target, $new_class ) {
	$index = null;
	foreach ( $classes as $i => $class_name ) {
		if ( $is_target( $class_name ) ) {
			$index = $index ?? $i;
			unset( $classes[ $i ] );
		}
	}
	if ( '' !== $new_class ) {
		if ( null === $index ) {
			$classes[] = $new_class;
		} else {
			$classes[ $index ] = $new_class;
			ksort( $classes );
		}
	}
	return array_values( $classes );
}

/**
 * Applique une déclinaison au bloc racine : attributs et classes du balisage.
 *
 * @param array  $block Bloc racine.
 * @param string $style Style ('' : par défaut).
 * @param string $align Alignement ('' : aucun).
 * @return array
 */
function waw_pattern_review_apply( array $block, $style, $align ) {
	$is_style = static fn( $c ) => str_starts_with( $c, 'is-style-' );
	$is_align = static fn( $c ) => in_array( $c, array( 'alignwide', 'alignfull' ), true );

	$classes = waw_pattern_review_replace_class(
		preg_split( '/\s+/', (string) ( $block['attrs']['className'] ?? '' ), -1, PREG_SPLIT_NO_EMPTY ),
		$is_style,
		'' === $style ? '' : 'is-style-' . $style
	);
	if ( $classes ) {
		$block['attrs']['className'] = implode( ' ', $classes );
	} else {
		unset( $block['attrs']['className'] );
	}

	if ( '' === $align ) {
		unset( $block['attrs']['align'] );
	} else {
		$block['attrs']['align'] = $align;
	}

	if ( isset( $block['innerContent'][0] ) && is_string( $block['innerContent'][0] ) ) {
		$block['innerContent'][0] = preg_replace_callback(
			'/class="([^"]*)"/',
			static function ( $m ) use ( $is_style, $is_align, $style, $align ) {
				$html = preg_split( '/\s+/', $m[1], -1, PREG_SPLIT_NO_EMPTY );
				$html = waw_pattern_review_replace_class( $html, $is_align, '' === $align ? '' : 'align' . $align );
				$html = waw_pattern_review_replace_class( $html, $is_style, '' === $style ? '' : 'is-style-' . $style );
				return 'class="' . implode( ' ', $html ) . '"';
			},
			$block['innerContent'][0],
			1
		);
	}

	return $block;
}

/**
 * Libellé lisible d'une déclinaison, dans la langue de l'utilisateur.
 *
 * @param string $block_name Bloc racine.
 * @param array  $variant    Déclinaison.
 * @return string
 */
function waw_pattern_review_variant_label( $block_name, array $variant ) {
	$name = '';
	if ( '' !== $variant['style'] ) {
		$registered = WP_Block_Styles_Registry::get_instance()->get_registered( $block_name, $variant['style'] );
		$name       = (string) ( $registered['label'] ?? $variant['style'] );
	}

	if ( $variant['default'] ) {
		/* translators: %s: libellé du style de base. */
		$style = '' === $name ? __( 'Par défaut', 'waw-pattern-review' ) : sprintf( __( 'Par défaut (%s)', 'waw-pattern-review' ), $name );
	} else {
		$style = $name;
	}

	$aligns = array(
		''     => __( 'Largeur du contenu', 'waw-pattern-review' ),
		'wide' => __( 'Large', 'waw-pattern-review' ),
		'full' => __( 'Pleine largeur', 'waw-pattern-review' ),
	);

	return $style . ' · ' . ( $aligns[ $variant['align'] ] ?? $variant['align'] );
}

/**
 * Toutes les déclinaisons d'une composition, avec leur balisage.
 *
 * @param array $pattern Composition.
 * @return array<int, array{label: string, style: string, align: string, delivered: bool, markup: string}>
 */
function waw_pattern_review_build( array $pattern ) {
	$blocks = waw_pattern_review_blocks( $pattern );
	if ( ! $blocks ) {
		return array();
	}

	$root   = $blocks[0];
	$others = array_slice( $blocks, 1 );
	$built  = array();

	foreach ( waw_pattern_review_variants( $pattern, $root ) as $variant ) {
		$markup = serialize_block( waw_pattern_review_apply( $root, $variant['style'], $variant['align'] ) );
		foreach ( $others as $other ) {
			$markup .= "\n\n" . serialize_block( $other );
		}
		$built[] = $variant + array(
			'label'  => waw_pattern_review_variant_label( $root['blockName'], $variant ),
			'markup' => $markup,
		);
	}

	return $built;
}
