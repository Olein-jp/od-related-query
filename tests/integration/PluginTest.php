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
}
