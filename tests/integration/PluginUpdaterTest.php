<?php
/**
 * GitHub Releases updater integration tests.
 *
 * @package OD_Related_Query
 */

/**
 * Verifies update checks without making requests to GitHub.
 */
class Plugin_Updater_Test extends WP_UnitTestCase {

	/**
	 * HTTP mock callback registered by a test.
	 *
	 * @var callable|null
	 */
	private $http_mock;

	/**
	 * Clears updater caches before each test.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->clear_updater_transients();
	}

	/**
	 * Removes HTTP mocks and updater caches after each test.
	 *
	 * @return void
	 */
	public function tear_down() {
		if ( $this->http_mock ) {
			remove_filter( 'pre_http_request', $this->http_mock );
			$this->http_mock = null;
		}

		$this->clear_updater_transients();

		parent::tear_down();
	}

	/**
	 * The updater registers with the WordPress update transient.
	 *
	 * @return void
	 */
	public function test_plugin_registers_github_release_updater() {
		$this->assertNotFalse(
			has_filter( 'pre_set_site_transient_update_plugins' )
		);
	}

	/**
	 * A newer public release is exposed as a WordPress plugin update.
	 *
	 * @return void
	 */
	public function test_newer_release_adds_plugin_update() {
		$this->mock_github_release( '0.2.0' );

		$transient = (object) array(
			'no_update' => array(),
			'response'  => array(),
		);
		$result    = apply_filters(
			'pre_set_site_transient_update_plugins',
			$transient
		);
		$plugin    = plugin_basename( OD_RELATED_QUERY_FILE );

		$this->assertArrayHasKey( $plugin, $result->response );
		$this->assertSame( '0.2.0', $result->response[ $plugin ]->new_version );
		$this->assertSame( '6.6', $result->response[ $plugin ]->requires );
		$this->assertSame( '7.4', $result->response[ $plugin ]->requires_php );
		$this->assertSame( '7.0', $result->response[ $plugin ]->tested );
		$this->assertSame(
			'https://example.com/od-related-query.zip',
			$result->response[ $plugin ]->package
		);
	}

	/**
	 * A release matching the installed version does not trigger an update.
	 *
	 * @return void
	 */
	public function test_current_release_does_not_add_plugin_update() {
		$this->mock_github_release( OD_RELATED_QUERY_VERSION );

		$transient = (object) array(
			'no_update' => array(),
			'response'  => array(),
		);
		$result    = apply_filters(
			'pre_set_site_transient_update_plugins',
			$transient
		);
		$plugin    = plugin_basename( OD_RELATED_QUERY_FILE );

		$this->assertArrayNotHasKey( $plugin, $result->response );
	}

	/**
	 * Plugin information includes compatibility data from the release.
	 *
	 * @return void
	 */
	public function test_plugin_information_includes_release_compatibility() {
		$this->mock_github_release( '0.2.0' );

		$information = apply_filters(
			'plugins_api',
			false,
			'plugin_information',
			(object) array( 'slug' => 'od-related-query' )
		);

		$this->assertStringContainsString( '0.2.0', $information->version );
		$this->assertSame( '6.6', $information->requires );
		$this->assertSame( '7.4', $information->requires_php );
		$this->assertSame( '7.0', $information->tested );
	}

	/**
	 * A GitHub API failure preserves update data from other providers.
	 *
	 * @return void
	 */
	public function test_api_failure_preserves_existing_update_data() {
		$existing_update = (object) array( 'new_version' => '2.0.0' );

		$this->http_mock = static function () {
			return new WP_Error( 'github_unavailable', 'GitHub unavailable.' );
		};
		add_filter( 'pre_http_request', $this->http_mock, 10, 3 );

		$transient = (object) array(
			'no_update' => array(),
			'response'  => array(
				'example/example.php' => $existing_update,
			),
		);
		$result    = apply_filters(
			'pre_set_site_transient_update_plugins',
			$transient
		);

		$this->assertSame(
			$existing_update,
			$result->response['example/example.php']
		);
		$this->assertArrayNotHasKey(
			plugin_basename( OD_RELATED_QUERY_FILE ),
			$result->response
		);
	}

	/**
	 * An invalid GitHub response does not replace existing update data.
	 *
	 * @return void
	 */
	public function test_invalid_api_response_preserves_existing_update_data() {
		$existing_update = (object) array( 'new_version' => '2.0.0' );

		$this->http_mock = static function () {
			return self::http_response( 'not-json' );
		};
		add_filter( 'pre_http_request', $this->http_mock, 10, 3 );

		$transient = (object) array(
			'no_update' => array(),
			'response'  => array(
				'example/example.php' => $existing_update,
			),
		);
		$result    = apply_filters(
			'pre_set_site_transient_update_plugins',
			$transient
		);

		$this->assertSame(
			$existing_update,
			$result->response['example/example.php']
		);
		$this->assertArrayNotHasKey(
			plugin_basename( OD_RELATED_QUERY_FILE ),
			$result->response
		);
	}

	/**
	 * Mocks the GitHub endpoints used during an update check.
	 *
	 * @param string $version Release tag.
	 * @return void
	 */
	private function mock_github_release( $version ) {
		$plugin_headers = <<<'PHP'
<?php
/**
 * Plugin Name: OD Related Query
 * Version: 0.2.0
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Tested up to: 7.0
 */
PHP;

		$this->http_mock = static function ( $response, $args, $url ) use ( $plugin_headers, $version ) {
			unset( $response, $args );

			if ( false !== strpos( $url, '/releases/latest' ) ) {
				return self::http_response(
					wp_json_encode(
						array(
							'assets'       => array(
								(object) array(
									'browser_download_url' => 'https://example.com/od-related-query.zip',
								),
							),
							'author'       => (object) array(
								'html_url' => 'https://github.com/Olein-jp',
								'login'    => 'Olein-jp',
							),
							'html_url'     => 'https://github.com/Olein-jp/od-related-query/releases/tag/' . $version,
							'published_at' => '2026-07-28T00:00:00Z',
							'tag_name'     => $version,
						)
					)
				);
			}

			if ( false !== strpos( $url, '/contents/od-related-query.php' ) ) {
				return self::http_response(
					wp_json_encode(
						// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- GitHub Contents API returns Base64-encoded file content.
						array( 'content' => base64_encode( $plugin_headers ) )
					)
				);
			}

			if ( 'https://example.com/od-related-query.zip' === $url ) {
				return self::http_response( '' );
			}

			return new WP_Error( 'unexpected_http_request', $url );
		};

		add_filter( 'pre_http_request', $this->http_mock, 10, 3 );
	}

	/**
	 * Creates a successful WordPress HTTP API response.
	 *
	 * @param string $body Response body.
	 * @return array
	 */
	private static function http_response( $body ) {
		return array(
			'body'     => $body,
			'cookies'  => array(),
			'filename' => null,
			'headers'  => array(),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
		);
	}

	/**
	 * Clears transients used by the updater dependency.
	 *
	 * @return void
	 */
	private function clear_updater_transients() {
		$plugin = plugin_basename( OD_RELATED_QUERY_FILE );

		delete_transient( 'wp_github_plugin_updater_' . $plugin );
		delete_transient(
			'wp_github_plugin_updater_repository_data_' . $plugin
		);
	}
}
