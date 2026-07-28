<?php
/**
 * Plugin Name:       OD Related Query
 * Description:       Adds a related-content variation to the Query Loop block.
 * Version:           0.1.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Tested up to:      7.0
 * Author:            Olein
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       od-related-query
 * Domain Path:       /languages
 * Update URI:        https://github.com/Olein-jp/od-related-query
 *
 * @package OD_Related_Query
 */

namespace OD_Related_Query;

defined( 'ABSPATH' ) || exit;

define( 'OD_RELATED_QUERY_FILE', __FILE__ );
define( 'OD_RELATED_QUERY_VERSION', '0.1.0' );

$od_related_query_autoloader = __DIR__ . '/vendor/autoload.php';

if ( file_exists( $od_related_query_autoloader ) ) {
	require_once $od_related_query_autoloader;
}

require_once __DIR__ . '/src/class-editor-assets.php';
require_once __DIR__ . '/src/class-plugin.php';
require_once __DIR__ . '/src/class-plugin-updater.php';
require_once __DIR__ . '/src/class-related-query.php';

add_action( 'plugins_loaded', array( Plugin::class, 'load' ) );
