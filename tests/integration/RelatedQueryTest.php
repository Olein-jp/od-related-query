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

		if ( post_type_exists( 'od_related_item' ) ) {
			unregister_post_type( 'od_related_item' );
		}

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
				'blockName'   => 'core/post-template',
				'attrs'       => array(),
				'innerBlocks' => array(),
			),
			array(
				'query' => array(
					Related_Query::REST_PARAMETER => 0,
				),
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
	 * A target post type selected in the Query Loop remains in effect.
	 *
	 * @return void
	 */
	public function test_selected_target_post_type_is_preserved() {
		register_post_type(
			'od_related_item',
			array(
				'public' => true,
			)
		);
		register_taxonomy_for_object_type( self::TAXONOMY, 'od_related_item' );

		$topic_id  = self::factory()->term->create(
			array(
				'taxonomy' => self::TAXONOMY,
			)
		);
		$source_id = self::factory()->post->create();
		$match_id  = self::factory()->post->create(
			array(
				'post_type' => 'od_related_item',
			)
		);

		wp_set_post_terms( $source_id, array( $topic_id ), self::TAXONOMY );
		wp_set_post_terms( $match_id, array( $topic_id ), self::TAXONOMY );

		$related_query = new Related_Query();
		$args          = $related_query->apply_related_arguments(
			array(
				'fields'    => 'ids',
				'post_type' => 'od_related_item',
			),
			$source_id
		);

		$this->assertSame( 'od_related_item', $args['post_type'] );
		$this->assertSame( array( $match_id ), get_posts( $args ) );
	}

	/**
	 * Cross-post-type queries use only public taxonomies shared by both types.
	 *
	 * @return void
	 */
	public function test_cross_post_type_query_uses_only_shared_taxonomies() {
		register_post_type(
			'od_related_item',
			array(
				'public' => true,
			)
		);
		register_taxonomy_for_object_type( self::TAXONOMY, 'od_related_item' );

		$category_id = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
			)
		);
		$topic_id    = self::factory()->term->create(
			array(
				'taxonomy' => self::TAXONOMY,
			)
		);
		$source_id   = self::factory()->post->create();
		$match_id    = self::factory()->post->create(
			array(
				'post_type' => 'od_related_item',
			)
		);

		wp_set_post_terms( $source_id, array( $category_id ), 'category' );
		wp_set_post_terms( $source_id, array( $topic_id ), self::TAXONOMY );
		wp_set_post_terms( $match_id, array( $topic_id ), self::TAXONOMY );

		$related_query = new Related_Query();
		$args          = $related_query->apply_related_arguments(
			array(
				'fields'    => 'ids',
				'post_type' => 'od_related_item',
			),
			$source_id,
			array(),
			array( 'invalid_saved_taxonomy' )
		);

		$this->assertCount( 2, $args['tax_query'] );
		$this->assertSame( self::TAXONOMY, $args['tax_query'][0]['taxonomy'] );
		$this->assertSame( array( $match_id ), get_posts( $args ) );
	}

	/**
	 * Cross-post-type queries without a shared taxonomy return no results.
	 *
	 * @return void
	 */
	public function test_cross_post_type_query_without_shared_taxonomy_returns_no_results() {
		register_post_type(
			'od_related_item',
			array(
				'public' => true,
			)
		);

		$category_id = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
			)
		);
		$source_id   = self::factory()->post->create();
		self::factory()->post->create(
			array(
				'post_type' => 'od_related_item',
			)
		);

		wp_set_post_terms( $source_id, array( $category_id ), 'category' );

		$related_query = new Related_Query();
		$args          = $related_query->apply_related_arguments(
			array(
				'fields'    => 'ids',
				'post_type' => 'od_related_item',
			),
			$source_id
		);

		$this->assertSame( array( 0 ), $args['post__in'] );
		$this->assertSame( array(), get_posts( $args ) );
	}

	/**
	 * Selected taxonomies limit which shared terms count as related.
	 *
	 * @return void
	 */
	public function test_selected_taxonomies_limit_relationship_matching() {
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

		wp_set_post_terms( $source_id, array( $category_id ), 'category' );
		wp_set_post_terms( $source_id, array( $topic_id ), self::TAXONOMY );
		wp_set_post_terms( $category_match_id, array( $category_id ), 'category' );
		wp_set_post_terms( $topic_match_id, array( $topic_id ), self::TAXONOMY );

		$related_query = new Related_Query();
		$args          = $related_query->apply_related_arguments(
			array(
				'fields' => 'ids',
			),
			$source_id,
			array( self::TAXONOMY )
		);

		$this->assertSame( array( $topic_match_id ), get_posts( $args ) );
		$this->assertNotContains( $category_match_id, get_posts( $args ) );
	}

	/**
	 * Explicitly excluded taxonomies do not count as relationship signals.
	 *
	 * @return void
	 */
	public function test_excluded_taxonomies_limit_relationship_matching() {
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

		wp_set_post_terms( $source_id, array( $category_id ), 'category' );
		wp_set_post_terms( $source_id, array( $topic_id ), self::TAXONOMY );
		wp_set_post_terms( $category_match_id, array( $category_id ), 'category' );
		wp_set_post_terms( $topic_match_id, array( $topic_id ), self::TAXONOMY );

		$related_query = new Related_Query();
		$args          = $related_query->apply_related_arguments(
			array(
				'fields' => 'ids',
			),
			$source_id,
			array(),
			array( 'category', 'post_tag' )
		);

		$this->assertSame( array( $topic_match_id ), get_posts( $args ) );
		$this->assertNotContains( $category_match_id, get_posts( $args ) );
	}

	/**
	 * Excluding every available taxonomy returns no related posts.
	 *
	 * @return void
	 */
	public function test_excluding_all_taxonomies_returns_no_results() {
		$category_id = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
			)
		);
		$topic_id    = self::factory()->term->create(
			array(
				'taxonomy' => self::TAXONOMY,
			)
		);
		$source_id   = self::factory()->post->create();
		self::factory()->post->create();

		wp_set_post_terms( $source_id, array( $category_id ), 'category' );
		wp_set_post_terms( $source_id, array( $topic_id ), self::TAXONOMY );

		$related_query = new Related_Query();
		$args          = $related_query->apply_related_arguments(
			array(
				'fields' => 'ids',
			),
			$source_id,
			array(),
			array( 'category', 'post_tag', self::TAXONOMY )
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
				'blockName'   => 'core/post-template',
				'attrs'       => array(),
				'innerBlocks' => array(),
			),
			array(
				'query' => array(
					'perPage' => 3,
				),
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
		$preview_id  = self::factory()->post->create();

		wp_set_post_terms( $source_id, array( $category_id ), 'category' );
		wp_set_post_terms( $match_id, array( $category_id ), 'category' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( Related_Query::REST_PARAMETER, $source_id );
		$request->set_param( Related_Query::REST_PREVIEW_PARAMETER, $preview_id );
		$request->set_param(
			Related_Query::REST_TAXONOMIES_PARAMETER,
			array( 'category' )
		);
		$request->set_param( 'per_page', 10 );

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $data );
		$this->assertSame( array( $match_id ), wp_list_pluck( $data, 'id' ) );
	}

	/**
	 * REST previews can use a template-editor-specific source post.
	 *
	 * @return void
	 */
	public function test_rest_query_uses_template_preview_source_parameter() {
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
		$request->set_param( Related_Query::REST_PARAMETER, 0 );
		$request->set_param( Related_Query::REST_PREVIEW_PARAMETER, $source_id );
		$request->set_param( 'per_page', 10 );

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $data );
		$this->assertSame( array( $match_id ), wp_list_pluck( $data, 'id' ) );
	}

	/**
	 * A saved template preview source never replaces the frontend source post.
	 *
	 * @return void
	 */
	public function test_frontend_ignores_template_preview_source_parameter() {
		$current_category_id = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
			)
		);
		$preview_category_id = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
			)
		);
		$current_id          = self::factory()->post->create();
		$current_match_id    = self::factory()->post->create();
		$preview_id          = self::factory()->post->create();
		$preview_match_id    = self::factory()->post->create();

		wp_set_post_terms( $current_id, array( $current_category_id ), 'category' );
		wp_set_post_terms( $current_match_id, array( $current_category_id ), 'category' );
		wp_set_post_terms( $preview_id, array( $preview_category_id ), 'category' );
		wp_set_post_terms( $preview_match_id, array( $preview_category_id ), 'category' );

		$this->go_to( get_permalink( $current_id ) );

		$block = new WP_Block(
			array(
				'blockName'   => 'core/post-template',
				'attrs'       => array(),
				'innerBlocks' => array(),
			),
			array(
				'query' => array(
					Related_Query::REST_PARAMETER         => 0,
					Related_Query::REST_PREVIEW_PARAMETER => $preview_id,
				),
			)
		);
		$args  = apply_filters(
			'query_loop_block_query_vars',
			array(
				'post_type'      => 'post',
				'posts_per_page' => 10,
				'fields'         => 'ids',
			),
			$block,
			1
		);
		$ids   = get_posts( $args );

		$this->assertContains( $current_match_id, $ids );
		$this->assertNotContains( $preview_match_id, $ids );
		$this->assertNotContains( $current_id, $ids );
	}
}
