<?php
/**
 * Plugin Name:       WAW : recette des compositions
 * Plugin URI:        https://www.wearewp.pro/
 * Description:       Recette visuelle des compositions d'un thème de blocs : chaque composition rendue à la volée avec toutes ses déclinaisons (styles, largeurs), en mobile, tablette et bureau, dans chaque langue, avec contrôle de validité des blocs par l'éditeur.
 * Version:           0.1.0
 * Requires at least: 7.1
 * Requires PHP:      8.1
 * Author:            WeAre[WP]
 * Author URI:        https://www.wearewp.pro/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        false
 * Text Domain:       waw-pattern-review
 *
 * @package WAW\PatternReview
 */

defined( 'ABSPATH' ) || exit;

const WAW_PATTERN_REVIEW_VERSION = '0.1.0';
const WAW_PATTERN_REVIEW_FILE    = __FILE__;

require_once __DIR__ . '/includes/variants.php';
require_once __DIR__ . '/includes/front.php';
require_once __DIR__ . '/includes/rest.php';
require_once __DIR__ . '/includes/admin.php';
