<?php
/**
 * Runs the local sample-content seeder through WP-CLI.
 *
 * @package OD_Related_Query
 */

namespace OD_Related_Query;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

require_once __DIR__ . '/class-sample-content-seeder.php';

$od_related_query_sample_seeder = new Sample_Content_Seeder();
$od_related_query_sample_seeder->run();
