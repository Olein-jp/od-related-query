<?php
/**
 * Main plugin bootstrap.
 *
 * @package OD_Related_Query
 */

namespace OD_Related_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates plugin initialization.
 */
final class Plugin {

	/**
	 * Runs after WordPress has loaded active plugins.
	 *
	 * @return void
	 */
	public static function load() {
		/**
		 * Fires after OD Related Query has initialized.
		 */
		do_action( 'od_related_query_loaded' );
	}
}
