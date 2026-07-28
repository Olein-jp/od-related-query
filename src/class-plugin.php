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
		$editor_assets = new Editor_Assets();
		$related_query = new Related_Query();

		add_action( 'init', array( self::class, 'load_textdomain' ) );
		$editor_assets->register_hooks();
		$related_query->register_hooks();

		/**
		 * Fires after OD Related Query has initialized.
		 */
		do_action( 'od_related_query_loaded' );
	}

	/**
	 * Loads bundled translations while preserving WordPress.org language packs.
	 *
	 * @return void
	 */
	public static function load_textdomain() {
		load_plugin_textdomain(
			'od-related-query',
			false,
			dirname( plugin_basename( OD_RELATED_QUERY_FILE ) ) . '/languages'
		);
	}
}
