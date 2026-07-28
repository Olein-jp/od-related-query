<?php
/**
 * Plugin bootstrap integration tests.
 *
 * @package OD_Related_Query
 */

/**
 * Verifies the public plugin bootstrap lifecycle.
 */
class Plugin_Test extends WP_UnitTestCase {

	/**
	 * The plugin announces that initialization completed.
	 *
	 * @return void
	 */
	public function test_plugin_fires_loaded_action_once() {
		$this->assertSame( 1, did_action( 'od_related_query_loaded' ) );
	}

	/**
	 * The plugin registers its Query Loop integration.
	 *
	 * @return void
	 */
	public function test_plugin_registers_query_loop_filter() {
		$this->assertNotFalse( has_filter( 'query_loop_block_query_vars' ) );
	}

	/**
	 * The compiled variation is available to block editors.
	 *
	 * @return void
	 */
	public function test_plugin_enqueues_editor_script() {
		do_action( 'enqueue_block_editor_assets' );

		$this->assertTrue(
			wp_script_is(
				OD_Related_Query\Editor_Assets::SCRIPT_HANDLE,
				'enqueued'
			)
		);
	}

	/**
	 * Bundled Japanese translations load for PHP strings.
	 *
	 * @return void
	 */
	public function test_plugin_loads_bundled_japanese_translations() {
		$determine_japanese_locale = static function () {
			return 'ja';
		};

		add_filter( 'pre_determine_locale', $determine_japanese_locale );
		unload_textdomain( 'od-related-query' );

		OD_Related_Query\Plugin::load_textdomain();
		$translation = __( 'Related content order', 'od-related-query' );

		remove_filter( 'pre_determine_locale', $determine_japanese_locale );
		unload_textdomain( 'od-related-query' );
		OD_Related_Query\Plugin::load_textdomain();

		$this->assertSame( '関連記事の並び順', $translation );
	}

	/**
	 * Bundled Japanese translations load for editor JavaScript.
	 *
	 * @return void
	 */
	public function test_plugin_loads_bundled_japanese_script_translations() {
		$determine_japanese_locale = static function () {
			return 'ja';
		};

		add_filter( 'pre_determine_locale', $determine_japanese_locale );
		do_action( 'enqueue_block_editor_assets' );

		$translations = load_script_textdomain(
			OD_Related_Query\Editor_Assets::SCRIPT_HANDLE,
			'od-related-query',
			dirname( OD_RELATED_QUERY_FILE ) . '/languages'
		);
		$locale_data  = json_decode( $translations, true );

		remove_filter( 'pre_determine_locale', $determine_japanese_locale );

		$this->assertSame(
			'関連記事の並び順',
			$locale_data['locale_data']['messages']['Related content order'][0]
		);
	}
}
