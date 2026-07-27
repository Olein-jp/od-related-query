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
}
