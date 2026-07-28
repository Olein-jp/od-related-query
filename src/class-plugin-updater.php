<?php
/**
 * GitHub Releases plugin updater.
 *
 * @package OD_Related_Query
 */

namespace OD_Related_Query;

use Inc2734\WP_GitHub_Plugin_Updater\Bootstrap;

defined( 'ABSPATH' ) || exit;

/**
 * Registers update checks for releases in the public GitHub repository.
 */
final class Plugin_Updater {

	/**
	 * Registered updater instance.
	 *
	 * @var Bootstrap|null
	 */
	private static $updater;

	/**
	 * Registers the updater once when its Composer dependency is available.
	 *
	 * @return void
	 */
	public function register() {
		if ( self::$updater || ! class_exists( Bootstrap::class ) ) {
			return;
		}

		self::$updater = new Bootstrap(
			plugin_basename( OD_RELATED_QUERY_FILE ),
			'Olein-jp',
			'od-related-query',
			array(
				'homepage'     => 'https://github.com/Olein-jp/od-related-query',
				'requires'     => '6.6',
				'requires_php' => '7.4',
				'tested'       => '7.0',
			)
		);
	}
}
