<?php
/**
 * Related-content query integration tests.
 *
 * @package OD_Related_Query
 */

use OD_Related_Query\Related_Query;

/**
 * Verifies taxonomy matching for frontend and REST queries.
 */
class Related_Query_Test extends WP_UnitTestCase {

	/**
	 * Custom taxonomy used by the test fixtures.
	 *
	 * @var string
	 */
	const TAXONOMY = 'od_related_topic';

	/**
	 * Registers the custom taxonomy fixture.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		register_taxonomy(
			self::TAXONOMY,
			'post',
			array(
				'public'       => true,
				'show_in_rest' => true,
			)
		);
	}

	/**
	 * Removes the custom taxonomy fixture.
	 *
	 * @return void
	 */
	public function tear_down() {
		unregister_taxonomy( self::TAXONOMY );
		parent::tear_down();
	}

	/**
	 * Related posts can match any public taxonomy and exclude the source post.
	 *
	 * @return void
	 */
	public function test_frontend_query_matches_any_viewable_taxonomy() {
		$category_id       = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
			)
		);
		$topic_id          = self::factory()->term->create(
			array(
				'taxonomy' => self::TAXONOMY,
			)
		);
		$source_id         = self::factory()->post->create();
		$category_match_id = self::factory()->post->create();
		$topic_match_id    = self::factory()->post->create();
		$unrelated_id      = self::factory()->post->create();

		wp_set_post_terms( $source_id, array( $category_id ), 'category' );
		wp_set_post_terms( $source_id, array( $topic_id ), self::TAXONOMY );
		wp_set_post_terms( $category_match_id, array( $category_id ), 'category' );
		wp_set_post_terms( $topic_match_id, array( $topic_id ), self::TAXONOMY );

		$this->go_to( get_permalink( $source_id ) );

		$block = new WP_Block(
			array(
				'blockName'   => 'core/query',
				'attrs'       => array(
					'namespace' => Related_Query::VARIATION_NAMESPACE,
				),
				'innerBlocks' => array(),
			)
		);
		$args  = apply_filters(
			'query_loop_block_query_vars',
			array(
				'post_type'      => 'post',
				'posts_per_page' => 3,
				'fields'         => 'ids',
			),
			$block,
			1
		);
		$ids   = get_posts( $args );

		$this->assertEqualSets(
			array( $category_match_id, $topic_match_id ),
			$ids
		);
		$this->assertNotContains( $source_id, $ids );
		$this->assertNotContains( $unrelated_id, $ids );
	}

	/**
	 * A source post without terms must not fall back to latest posts.
	 *
	 * @return void
	 */
	public function test_post_without_terms_returns_no_results() {
		$source_id = self::factory()->post->create();
		self::factory()->post->create();
		wp_set_post_terms( $source_id, array(), 'category' );

		$related_query = new Related_Query();
		$args          = $related_query->apply_related_arguments(
			array(
				'fields' => 'ids',
			),
			$source_id
		);

		$this->assertSame( array( 0 ), $args['post__in'] );
		$this->assertSame( array(), get_posts( $args ) );
	}

	/**
	 * Other Query Loop blocks are not modified.
	 *
	 * @return void
	 */
	public function test_other_query_loop_namespaces_are_unchanged() {
		$source_id = self::factory()->post->create();
		$this->go_to( get_permalink( $source_id ) );

		$block = new WP_Block(
			array(
				'blockName'   => 'core/query',
				'attrs'       => array(
					'namespace' => 'another-plugin/query',
				),
				'innerBlocks' => array(),
			)
		);
		$args  = array(
			'post_type'      => 'post',
			'posts_per_page' => 3,
		);

		$this->assertSame(
			$args,
			apply_filters( 'query_loop_block_query_vars', $args, $block, 1 )
		);
	}

	/**
	 * REST previews use the same source-post constraints.
	 *
	 * @return void
	 */
	public function test_rest_query_uses_related_source_parameter() {
		$category_id = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
			)
		);
		$source_id   = self::factory()->post->create();
		$match_id    = self::factory()->post->create();
		self::factory()->post->create();

		wp_set_post_terms( $source_id, array( $category_id ), 'category' );
		wp_set_post_terms( $match_id, array( $category_id ), 'category' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( Related_Query::REST_PARAMETER, $source_id );
		$request->set_param( 'per_page', 10 );

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $data );
		$this->assertSame( array( $match_id ), wp_list_pluck( $data, 'id' ) );
	}
}
