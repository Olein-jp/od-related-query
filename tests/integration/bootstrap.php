<?php
/**
 * Bootstrap the WordPress integration test suite.
 *
 * @package OD_Related_Query
 */

$od_related_query_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $od_related_query_tests_dir ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test bootstrap error.
	echo "WP_TESTS_DIR is not set.\n";
	exit( 1 );
}

require_once $od_related_query_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__, 2 ) . '/od-related-query.php';
	}
);

require $od_related_query_tests_dir . '/includes/bootstrap.php';
