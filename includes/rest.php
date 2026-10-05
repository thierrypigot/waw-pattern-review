<?php
/**
 * Route REST : balisage de chaque déclinaison, pour le contrôle de validité
 * fait dans l'écran de recette par le code de l'éditeur (wp.blocks.parse()).
 *
 * GET /waw-pattern-review/v1/variants[?slug=<composition>]
 *
 * @package WAW\PatternReview
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', 'waw_pattern_review_rest_routes' );
/**
 * Enregistre la route.
 */
function waw_pattern_review_rest_routes() {
	register_rest_route(
		'waw-pattern-review/v1',
		'/variants',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'waw_pattern_review_rest_variants',
			'permission_callback' => static fn() => current_user_can( 'edit_theme_options' ),
			'args'                => array(
				'slug' => array(
					'type'              => 'string',
					'required'          => false,
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => static fn( $value ) => '' === $value || (bool) preg_match( '#^[a-z0-9-]+/[a-z0-9-]+$#', $value ),
				),
			),
		)
	);
}

/**
 * Déclinaisons d'une composition, ou de toutes.
 *
 * @param WP_REST_Request $request Requête.
 * @return WP_REST_Response|WP_Error
 */
function waw_pattern_review_rest_variants( WP_REST_Request $request ) {
	$slug     = (string) $request->get_param( 'slug' );
	$patterns = waw_pattern_review_patterns();

	if ( '' !== $slug ) {
		if ( ! isset( $patterns[ $slug ] ) ) {
			return new WP_Error( 'waw_pattern_review_not_found', __( 'Composition introuvable.', 'waw-pattern-review' ), array( 'status' => 404 ) );
		}
		$patterns = array( $slug => $patterns[ $slug ] );
	}

	$data = array();
	foreach ( $patterns as $pattern ) {
		$data[] = array(
			'slug'     => $pattern['slug'],
			'title'    => waw_pattern_review_texts( $pattern )['title'],
			'variants' => array_map(
				static fn( $variant ) => array(
					'label'  => $variant['label'],
					'markup' => $variant['markup'],
				),
				waw_pattern_review_build( $pattern )
			),
		);
	}

	return rest_ensure_response( $data );
}
