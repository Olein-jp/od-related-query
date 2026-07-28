<?php
/**
 * Related-content query integration.
 *
 * @package OD_Related_Query
 */

namespace OD_Related_Query;

use WP_Block;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Builds related-content queries for the Query Loop variation.
 */
final class Related_Query {

	/**
	 * Query Loop variation namespace.
	 *
	 * @var string
	 */
	const VARIATION_NAMESPACE = 'od-related-query/related';

	/**
	 * REST API parameter used by editor previews.
	 *
	 * @var string
	 */
	const REST_PARAMETER = 'od_related_to';

	/**
	 * REST API parameter used to limit relationship taxonomies.
	 *
	 * @var string
	 */
	const REST_TAXONOMIES_PARAMETER = 'od_related_taxonomies';

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'query_loop_block_query_vars', array( $this, 'filter_block_query' ), 10, 3 );
		add_action( 'init', array( $this, 'register_rest_filters' ), 100 );
	}

	/**
	 * Adds related-content arguments to this plugin's Query Loop variation.
	 *
	 * @param array<string, mixed> $query Query arguments.
	 * @param WP_Block             $block Block instance used to build the query.
	 * @param int                  $page  Current query page.
	 * @return array<string, mixed>
	 */
	public function filter_block_query( $query, $block, $page ) {
		unset( $page );

		$block_query = $block->context['query'] ?? array();

		if (
			! is_array( $block_query )
			|| ! array_key_exists( self::REST_PARAMETER, $block_query )
			|| ! is_singular()
		) {
			return $query;
		}

		$taxonomies = $block_query[ self::REST_TAXONOMIES_PARAMETER ] ?? array();

		return $this->apply_related_arguments(
			$query,
			get_queried_object_id(),
			is_array( $taxonomies ) ? $taxonomies : array()
		);
	}

	/**
	 * Registers REST query filters for post types available to the editor.
	 *
	 * @return void
	 */
	public function register_rest_filters() {
		$post_types = get_post_types(
			array(
				'show_in_rest' => true,
			),
			'names'
		);

		foreach ( $post_types as $post_type ) {
			add_filter(
				"rest_{$post_type}_collection_params",
				array( $this, 'filter_rest_collection_params' )
			);
			add_filter(
				"rest_{$post_type}_query",
				array( $this, 'filter_rest_query' ),
				10,
				2
			);
		}
	}

	/**
	 * Exposes the related-post ID to Query Loop REST requests.
	 *
	 * @param array<string, mixed> $query_params Collection parameter schema.
	 * @return array<string, mixed>
	 */
	public function filter_rest_collection_params( $query_params ) {
		$query_params[ self::REST_PARAMETER ]            = array(
			'description'       => __( 'Post ID used as the source for related content.', 'od-related-query' ),
			'type'              => 'integer',
			'minimum'           => 0,
			'sanitize_callback' => 'absint',
		);
		$query_params[ self::REST_TAXONOMIES_PARAMETER ] = array(
			'description' => __( 'Taxonomies used to determine related content.', 'od-related-query' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
		);

		return $query_params;
	}

	/**
	 * Applies related-content arguments to an editor REST query.
	 *
	 * @param array<string, mixed> $args    Query arguments.
	 * @param WP_REST_Request      $request REST request.
	 * @return array<string, mixed>
	 */
	public function filter_rest_query( $args, $request ) {
		if ( ! $request->has_param( self::REST_PARAMETER ) ) {
			return $args;
		}

		$taxonomies = $request->get_param( self::REST_TAXONOMIES_PARAMETER );

		return $this->apply_related_arguments(
			$args,
			absint( $request->get_param( self::REST_PARAMETER ) ),
			is_array( $taxonomies ) ? $taxonomies : array()
		);
	}

	/**
	 * Applies the query constraints shared by frontend and editor requests.
	 *
	 * @param array<string, mixed> $query                Existing query arguments.
	 * @param int                  $post_id              Source post ID.
	 * @param array<int, string>   $selected_taxonomies Taxonomies used for matching.
	 * @return array<string, mixed>
	 */
	public function apply_related_arguments( $query, $post_id, $selected_taxonomies = array() ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			$query['post__in'] = array( 0 );
			return $query;
		}

		$excluded_posts = array();

		if ( isset( $query['post__not_in'] ) && is_array( $query['post__not_in'] ) ) {
			$excluded_posts = array_map( 'absint', $query['post__not_in'] );
		}

		$excluded_posts[] = $post_id;
		if (
			! isset( $query['post_type'] )
			|| ! is_string( $query['post_type'] )
			|| ! post_type_exists( $query['post_type'] )
		) {
			$query['post_type'] = $post->post_type;
		}

		$query['post__not_in'] = array_values( array_unique( $excluded_posts ) );

		$tax_query           = array( 'relation' => 'OR' );
		$taxonomies          = get_object_taxonomies( $post->post_type, 'objects' );
		$selected_taxonomies = array_filter(
			array_map( 'sanitize_key', $selected_taxonomies )
		);

		foreach ( $taxonomies as $taxonomy ) {
			if (
				! is_taxonomy_viewable( $taxonomy )
				|| (
					! empty( $selected_taxonomies )
					&& ! in_array( $taxonomy->name, $selected_taxonomies, true )
				)
			) {
				continue;
			}

			$term_ids = wp_get_post_terms(
				$post_id,
				$taxonomy->name,
				array( 'fields' => 'ids' )
			);

			if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
				continue;
			}

			$tax_query[] = array(
				'taxonomy' => $taxonomy->name,
				'field'    => 'term_id',
				'terms'    => array_map( 'absint', $term_ids ),
			);
		}

		if ( 1 === count( $tax_query ) ) {
			$query['post__in'] = array( 0 );
			unset( $query['tax_query'] );
			return $query;
		}

		unset( $query['post__in'] );
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Taxonomy matching is the plugin's core behavior.
		$query['tax_query'] = $tax_query;

		return $query;
	}
}
