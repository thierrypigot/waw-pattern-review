<?php
/**
 * Écran Apparence > Recette : liste des compositions, aperçus en mobile,
 * tablette et bureau, contrôle de validité des blocs.
 *
 * @package WAW\PatternReview
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'waw_pattern_review_menu' );
/**
 * Entrée de menu sous Apparence.
 */
function waw_pattern_review_menu() {
	$hook = add_theme_page(
		__( 'Recette des compositions', 'waw-pattern-review' ),
		__( 'Recette', 'waw-pattern-review' ),
		'edit_theme_options',
		'waw-pattern-review',
		'waw_pattern_review_screen'
	);

	add_action( 'admin_enqueue_scripts', static fn( $current ) => $current === $hook && waw_pattern_review_assets() );
}

/**
 * Langues de l'aperçu : celles de Polylang, sinon la langue du site.
 *
 * @return array<int, array{slug: string, name: string}>
 */
function waw_pattern_review_languages() {
	if ( function_exists( 'pll_languages_list' ) ) {
		$slugs = (array) pll_languages_list( array( 'fields' => 'slug' ) );
		$names = (array) pll_languages_list( array( 'fields' => 'name' ) );
		if ( $slugs ) {
			return array_map( static fn( $slug, $name ) => array( 'slug' => $slug, 'name' => $name ), $slugs, $names );
		}
	}

	return array(
		array(
			'slug' => '',
			'name' => get_locale(),
		),
	);
}

/**
 * Largeurs d'aperçu, avec les pictogrammes de l'aperçu de l'éditeur
 * (@wordpress/icons : desktop, tablet, mobile ; aucun script du cœur ne les
 * expose en global, les tracés sont repris tels quels).
 *
 * @return array<string, array{width: int, label: string, icon: string}>
 */
function waw_pattern_review_devices() {
	return array(
		'desktop' => array(
			'width' => 1440,
			'label' => __( 'Bureau', 'waw-pattern-review' ),
			'icon'  => 'M20.5 16h-.7V8c0-1.1-.9-2-2-2H6.2c-1.1 0-2 .9-2 2v8h-.7c-.8 0-1.5.7-1.5 1.5h20c0-.8-.7-1.5-1.5-1.5zM5.7 8c0-.3.2-.5.5-.5h11.6c.3 0 .5.2.5.5v7.6H5.7V8z',
		),
		'tablet'  => array(
			'width' => 834,
			'label' => __( 'Tablette', 'waw-pattern-review' ),
			'icon'  => 'M17 4H7c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm.5 14c0 .3-.2.5-.5.5H7c-.3 0-.5-.2-.5-.5V6c0-.3.2-.5.5-.5h10c.3 0 .5.2.5.5v12zm-7.5-.5h4V16h-4v1.5z',
		),
		'mobile'  => array(
			'width' => 390,
			'label' => __( 'Mobile', 'waw-pattern-review' ),
			'icon'  => 'M15 4H9c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h6c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm.5 14c0 .3-.2.5-.5.5H9c-.3 0-.5-.2-.5-.5V6c0-.3.2-.5.5-.5h6c.3 0 .5.2.5.5v12zm-4.5-.5h2V16h-2v1.5z',
		),
	);
}

/**
 * Composition affichée : celle de l'URL, sinon la première.
 *
 * @param array $patterns Compositions.
 * @return string
 */
function waw_pattern_review_current_slug( array $patterns ) {
	$slug = isset( $_GET['pattern'] ) ? sanitize_text_field( wp_unslash( $_GET['pattern'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule.
	return isset( $patterns[ $slug ] ) ? $slug : (string) array_key_first( $patterns );
}

/**
 * Scripts et styles de l'écran.
 */
function waw_pattern_review_assets() {
	$patterns  = waw_pattern_review_patterns();
	$current   = waw_pattern_review_current_slug( $patterns );
	$languages = waw_pattern_review_languages();

	$urls = array();
	foreach ( $languages as $language ) {
		$urls[ $language['slug'] ] = waw_pattern_review_url( $current, array( 'waw_pr_embed' => 1 ), $language['slug'] );
	}

	$base = plugins_url( 'assets/', WAW_PATTERN_REVIEW_FILE );
	$dir  = plugin_dir_path( WAW_PATTERN_REVIEW_FILE ) . 'assets/';

	wp_enqueue_style( 'waw-pattern-review', $base . 'admin.css', array( 'wp-components' ), (string) filemtime( $dir . 'admin.css' ) );

	// wp-block-editor avant l'enregistrement des blocs : il ajoute les attributs
	// et classes des supports (couleurs, espacements…) que save() produit.
	wp_enqueue_script(
		'waw-pattern-review',
		$base . 'admin.js',
		array( 'wp-api-fetch', 'wp-i18n', 'wp-blocks', 'wp-block-editor', 'wp-block-library', 'wp-dom-ready' ),
		(string) filemtime( $dir . 'admin.js' ),
		true
	);
	wp_set_script_translations( 'waw-pattern-review', 'waw-pattern-review' );
	wp_add_inline_script(
		'waw-pattern-review',
		'window.wawPatternReview = ' . wp_json_encode(
			array(
				'current' => $current,
				'urls'    => $urls,
				'devices' => array_map( static fn( $device ) => array( 'width' => $device['width'], 'label' => $device['label'] ), waw_pattern_review_devices() ),
			),
			JSON_HEX_TAG | JSON_UNESCAPED_SLASHES
		) . ';',
		'before'
	);
}

/**
 * Écran de recette.
 */
function waw_pattern_review_screen() {
	$patterns  = waw_pattern_review_patterns();
	$current   = waw_pattern_review_current_slug( $patterns );
	$languages = waw_pattern_review_languages();
	?>
	<div class="wrap waw-pr">
		<h1><?php esc_html_e( 'Recette des compositions', 'waw-pattern-review' ); ?></h1>

		<?php if ( ! wp_is_block_theme() ) : ?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'La recette demande un thème de blocs.', 'waw-pattern-review' ); ?></p></div>
		<?php elseif ( ! $patterns ) : ?>
			<p><?php esc_html_e( 'Le thème actif ne déclare aucune composition dans son dossier patterns/.', 'waw-pattern-review' ); ?></p>
		<?php else : ?>
		<div class="waw-pr__layout">
			<nav class="waw-pr__list" aria-label="<?php esc_attr_e( 'Compositions', 'waw-pattern-review' ); ?>">
				<ul>
					<?php foreach ( $patterns as $slug => $pattern ) : ?>
						<?php
						$count = count( waw_pattern_review_build( $pattern ) );
						$url   = add_query_arg(
							array(
								'page'    => 'waw-pattern-review',
								'pattern' => $slug,
							),
							admin_url( 'themes.php' )
						);
						?>
						<li>
							<a href="<?php echo esc_url( $url ); ?>"<?php echo $slug === $current ? ' aria-current="page"' : ''; ?>>
								<span class="waw-pr__name"><?php echo esc_html( waw_pattern_review_texts( $pattern )['title'] ); ?></span>
								<span class="waw-pr__count">
									<?php
									/* translators: %d: nombre de déclinaisons. */
									echo esc_html( sprintf( _n( '%d déclinaison', '%d déclinaisons', $count, 'waw-pattern-review' ), $count ) );
									?>
								</span>
								<span class="waw-pr__status" data-status-for="<?php echo esc_attr( $slug ); ?>"></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
				<p><button type="button" class="button" data-action="validate-all"><?php esc_html_e( 'Tout valider', 'waw-pattern-review' ); ?></button></p>
				<p class="waw-pr__summary" role="status" aria-live="polite"></p>
			</nav>

			<section class="waw-pr__main" aria-labelledby="waw-pr-title">
				<div class="waw-pr__toolbar">
					<h2 id="waw-pr-title"><?php echo esc_html( waw_pattern_review_texts( $patterns[ $current ] )['title'] ); ?> <code><?php echo esc_html( $current ); ?></code></h2>

					<?php if ( count( $languages ) > 1 ) : ?>
						<label>
							<span class="screen-reader-text"><?php esc_html_e( 'Langue', 'waw-pattern-review' ); ?></span>
							<select data-control="lang">
								<?php foreach ( $languages as $language ) : ?>
									<option value="<?php echo esc_attr( $language['slug'] ); ?>"><?php echo esc_html( $language['name'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					<?php endif; ?>


					<label class="waw-pr__chrome"><input type="checkbox" data-control="chrome" checked> <?php esc_html_e( 'En-tête et pied', 'waw-pattern-review' ); ?></label>

					<a class="button button-small" data-control="open" href="<?php echo esc_url( waw_pattern_review_url( $current ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Ouvrir dans un onglet', 'waw-pattern-review' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(nouvel onglet)', 'waw-pattern-review' ); ?></span></a>

					<div class="waw-pr__devices" role="group" aria-label="<?php esc_attr_e( 'Largeur d’écran', 'waw-pattern-review' ); ?>">
						<?php foreach ( waw_pattern_review_devices() as $device => $data ) : ?>
							<button type="button" class="components-button has-icon" data-view="<?php echo esc_attr( $device ); ?>" aria-pressed="<?php echo 'desktop' === $device ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( $data['label'] ); ?>" title="<?php echo esc_attr( $data['label'] ); ?>">
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="<?php echo esc_attr( $data['icon'] ); ?>"/></svg>
							</button>
						<?php endforeach; ?>
					</div>
				</div>

				<details class="waw-pr__checks">
					<summary><?php esc_html_e( 'Validité des blocs', 'waw-pattern-review' ); ?> <span class="waw-pr__checks-summary" aria-live="polite"><?php esc_html_e( 'contrôle en cours…', 'waw-pattern-review' ); ?></span></summary>
					<ol></ol>
				</details>

				<div class="waw-pr__frames"></div>
			</section>
		</div>
		<?php endif; ?>
	</div>
	<?php
}
