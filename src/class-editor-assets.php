<?php
/**
 * Block editor assets.
 *
 * @package OD_Related_Query
 */

namespace OD_Related_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the Query Loop variation in block editors.
 */
final class Editor_Assets {

	/**
	 * Editor script handle.
	 *
	 * @var string
	 */
	const SCRIPT_HANDLE = 'od-related-query-editor';

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueues the compiled variation script.
	 *
	 * @return void
	 */
	public function enqueue() {
		$asset_path = dirname( OD_RELATED_QUERY_FILE ) . '/build/index.asset.php';

		if ( ! file_exists( $asset_path ) ) {
			return;
		}

		$asset = require $asset_path;

		if (
			! is_array( $asset )
			|| ! isset( $asset['dependencies'], $asset['version'] )
			|| ! is_array( $asset['dependencies'] )
		) {
			return;
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			plugins_url( 'build/index.js', OD_RELATED_QUERY_FILE ),
			$asset['dependencies'],
			(string) $asset['version'],
			true
		);

		wp_set_script_translations(
			self::SCRIPT_HANDLE,
			'od-related-query',
			dirname( OD_RELATED_QUERY_FILE ) . '/languages'
		);
	}
}
