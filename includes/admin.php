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
	add_action( 'load-' . $hook, 'waw_pattern_review_tab_screen' );
}

/**
 * Vue « onglet » de l'écran (&view=tab) : aperçu plein fenêtre, sans menus
 * ni barre d'administration, réglages dans une barre flottante.
 *
 * @return bool
 */
function waw_pattern_review_is_tab() {
	return isset( $_GET['view'] ) && 'tab' === $_GET['view']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- lecture seule, comparaison stricte.
}

/**
 * URL de la vue « onglet ».
 *
 * @param string $slug Nom de la composition.
 * @param array  $args Réglages initiaux (lang, device, chrome, variant).
 * @return string
 */
function waw_pattern_review_tab_url( $slug, array $args = array() ) {
	return add_query_arg(
		array_merge(
			array(
				'page'    => 'waw-pattern-review',
				'pattern' => $slug,
				'view'    => 'tab',
			),
			$args
		),
		admin_url( 'themes.php' )
	);
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

	$devices = array_map( static fn( $device ) => array( 'width' => $device['width'], 'label' => $device['label'] ), waw_pattern_review_devices() );

	if ( waw_pattern_review_is_tab() ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- lecture seule, valeurs contrôlées ci-dessous.
		$lang   = isset( $_GET['lang'] ) ? sanitize_key( wp_unslash( $_GET['lang'] ) ) : '';
		$device = isset( $_GET['device'] ) ? sanitize_key( wp_unslash( $_GET['device'] ) ) : '';
		$state  = array(
			'lang'    => isset( $urls[ $lang ] ) ? $lang : (string) array_key_first( $urls ),
			'view'    => isset( $devices[ $device ] ) ? $device : 'desktop',
			'chrome'  => ! ( isset( $_GET['chrome'] ) && '0' === $_GET['chrome'] ),
			'variant' => isset( $_GET['variant'] ) ? absint( $_GET['variant'] ) : 0,
		);
		// phpcs:enable

		wp_enqueue_script( 'waw-pattern-review-tab', $base . 'tab.js', array(), (string) filemtime( $dir . 'tab.js' ), true );
		wp_add_inline_script(
			'waw-pattern-review-tab',
			'window.wawPatternReviewTab = ' . wp_json_encode(
				array(
					'urls'    => $urls,
					'devices' => $devices,
					'state'   => $state,
				),
				JSON_HEX_TAG | JSON_UNESCAPED_SLASHES
			) . ';',
			'before'
		);
		return;
	}

	// wp-block-editor avant l'enregistrement des blocs : il ajoute les attributs
	// et classes des supports (couleurs, espacements…) que save() produit.
	wp_enqueue_script(
		'waw-pattern-review',
		$base . 'admin.js',
		array( 'wp-api-fetch', 'wp-i18n', 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-block-library', 'wp-dom-ready' ),
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
				'tabUrl'       => waw_pattern_review_tab_url( $current ),
				'devices'      => $devices,
				'serverBlocks' => waw_pattern_review_server_blocks(),
			),
			JSON_HEX_TAG | JSON_UNESCAPED_SLASHES
		) . ';',
		'before'
	);
}

/**
 * Blocs dynamiques des extensions et du thème (hors cœur), pour le contrôle
 * de validité.
 *
 * L'écran de recette n'enregistre que les blocs du cœur : sans cela, le bloc
 * dynamique d'une extension (rendu par le serveur, balisage réduit à un
 * commentaire et à ses éventuels blocs internes) serait signalé « non
 * disponible ». Sa définition serveur (attributs, supports) suffit à le
 * déclarer côté navigateur, comme le fait l'éditeur. Les blocs statiques
 * des extensions restent signalés : leur save() n'est pas disponible ici.
 *
 * @return array<int, array{name: string, title: string, attributes: array, supports: array}>
 */
function waw_pattern_review_server_blocks() {
	$blocks = array();
	foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type ) {
		if ( str_starts_with( $name, 'core/' ) || ! $type->is_dynamic() ) {
			continue;
		}
		$blocks[] = array(
			'name'       => $name,
			'title'      => (string) ( $type->title ? $type->title : $name ),
			'attributes' => (array) $type->attributes,
			'supports'   => (array) $type->supports,
		);
	}
	return $blocks;
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

					<a class="button button-small" data-control="open" href="<?php echo esc_url( waw_pattern_review_tab_url( $current ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Ouvrir dans un onglet', 'waw-pattern-review' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(nouvel onglet)', 'waw-pattern-review' ); ?></span></a>

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

/**
 * Vue « onglet » : rendue à la place de l'écran, dans le cadre minimal de
 * l'administration (styles et boutons de l'écran, sans menus ni barre).
 */
function waw_pattern_review_tab_screen() {
	$patterns = waw_pattern_review_patterns();
	if ( ! waw_pattern_review_is_tab() || ! wp_is_block_theme() || ! $patterns ) {
		return;
	}

	$current   = waw_pattern_review_current_slug( $patterns );
	$languages = waw_pattern_review_languages();
	$title     = waw_pattern_review_texts( $patterns[ $current ] )['title'];
	$back      = add_query_arg(
		array(
			'page'    => 'waw-pattern-review',
			'pattern' => $current,
		),
		admin_url( 'themes.php' )
	);

	/* translators: %s: titre de la composition. */
	iframe_header( sprintf( __( 'Recette : %s', 'waw-pattern-review' ), $title ) );
	?>
	<div class="waw-pr waw-pr-tab">
		<div class="waw-pr-tab__stage">
			<?php /* translators: %s: titre de la composition. */ ?>
			<iframe data-control="frame" title="<?php echo esc_attr( sprintf( __( 'Aperçu : %s', 'waw-pattern-review' ), $title ) ); ?>"></iframe>
		</div>

		<div class="waw-pr__toolbar waw-pr-tab__bar" role="region" aria-label="<?php esc_attr_e( 'Réglages de l’aperçu', 'waw-pattern-review' ); ?>">
			<div class="waw-pr-tab__controls" id="waw-pr-tab-controls">
				<h1><?php echo esc_html( $title ); ?> <code><?php echo esc_html( $current ); ?></code></h1>

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

				<a class="button button-small" href="<?php echo esc_url( $back ); ?>"><?php esc_html_e( 'Retour à la recette', 'waw-pattern-review' ); ?></a>

				<div class="waw-pr__devices" role="group" aria-label="<?php esc_attr_e( 'Largeur d’écran', 'waw-pattern-review' ); ?>">
					<?php foreach ( waw_pattern_review_devices() as $device => $data ) : ?>
						<button type="button" class="components-button has-icon" data-view="<?php echo esc_attr( $device ); ?>" aria-pressed="<?php echo 'desktop' === $device ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( $data['label'] ); ?>" title="<?php echo esc_attr( $data['label'] ); ?>">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="<?php echo esc_attr( $data['icon'] ); ?>"/></svg>
						</button>
					<?php endforeach; ?>
				</div>
			</div>

			<?php // Pictogrammes chevronDown et chevronUp de @wordpress/icons. ?>
			<button type="button" class="components-button has-icon waw-pr-tab__toggle" data-control="toggle" aria-expanded="true" aria-controls="waw-pr-tab-controls" aria-label="<?php esc_attr_e( 'Réduire la barre', 'waw-pattern-review' ); ?>" title="<?php esc_attr_e( 'Réduire la barre', 'waw-pattern-review' ); ?>" data-label-collapse="<?php esc_attr_e( 'Réduire la barre', 'waw-pattern-review' ); ?>" data-label-expand="<?php esc_attr_e( 'Afficher les réglages', 'waw-pattern-review' ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path class="waw-pr-tab__down" d="M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.6-3.6 1 1.2z"/><path class="waw-pr-tab__up" d="M6.5 12.4L12 8l5.5 4.4-.9 1.2L12 10l-4.6 3.6-1-1.2z"/></svg>
			</button>
		</div>
	</div>
	<?php
	iframe_footer();
	exit;
}
