<?php
/**
 * Plugin Name:       OD Related Query
 * Description:       Provides the foundation for related-content queries.
 * Version:           0.1.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Olein
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       od-related-query
 *
 * @package OD_Related_Query
 */

namespace OD_Related_Query;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/src/class-plugin.php';

add_action( 'plugins_loaded', array( Plugin::class, 'load' ) );
