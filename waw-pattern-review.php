<?php
/**
 * Plugin Name:       WAW : recette des compositions
 * Plugin URI:        https://www.wearewp.pro/
 * Description:       Recette visuelle des compositions d'un thème de blocs : chaque composition rendue à la volée avec toutes ses déclinaisons (styles, largeurs), en mobile, tablette et bureau, dans chaque langue, avec contrôle de validité des blocs par l'éditeur.
 * Version:           0.2.1
 * Requires at least: 7.1
 * Requires PHP:      8.1
 * Author:            WeAre[WP]
 * Author URI:        https://www.wearewp.pro/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://github.com/thierrypigot/waw-pattern-review/
 * Text Domain:       waw-pattern-review
 *
 * @package WAW\PatternReview
 */

defined( 'ABSPATH' ) || exit;

const WAW_PATTERN_REVIEW_VERSION = '0.2.1';
const WAW_PATTERN_REVIEW_FILE    = __FILE__;

require_once __DIR__ . '/includes/variants.php';
require_once __DIR__ . '/includes/front.php';
require_once __DIR__ . '/includes/rest.php';
require_once __DIR__ . '/includes/admin.php';

// Mises à jour depuis les releases GitHub (ZIP joint à la release). Le
// sous-module manque dans un clone sans --recurse-submodules : pas de mise à
// jour, mais pas d'erreur fatale.
if ( is_readable( __DIR__ . '/plugin-update-checker/plugin-update-checker.php' ) ) {
	require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';

	YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/thierrypigot/waw-pattern-review/',
		__FILE__,
		'waw-pattern-review'
	)->getVcsApi()->enableReleaseAssets();
}
