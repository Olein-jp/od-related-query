<?php
/**
 * Local sample-content seeder.
 *
 * @package OD_Related_Query
 */

namespace OD_Related_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Creates deterministic content for manually testing related queries.
 */
final class Sample_Content_Seeder {

	/**
	 * Meta key assigned to generated posts.
	 *
	 * @var string
	 */
	const POST_META_KEY = '_od_related_query_sample_post';

	/**
	 * Meta key assigned to generated media.
	 *
	 * @var string
	 */
	const IMAGE_META_KEY = '_od_related_query_sample_image';

	/**
	 * Meta key assigned to the generated template override.
	 *
	 * @var string
	 */
	const TEMPLATE_META_KEY = '_od_related_query_sample_template';

	/**
	 * Creates or updates all sample data.
	 *
	 * @return void
	 */
	public function run() {
		$categories = $this->ensure_terms(
			'category',
			array(
				'wordpress'          => 'WordPress',
				'plugin-development' => __( 'Plugin Development', 'od-related-query' ),
				'block-themes'       => __( 'Block Themes', 'od-related-query' ),
				'performance'        => __( 'Performance', 'od-related-query' ),
			)
		);
		$tags       = $this->ensure_terms(
			'post_tag',
			array(
				'gutenberg'  => 'Gutenberg',
				'query-loop' => 'Query Loop',
				'taxonomy'   => 'Taxonomy',
				'php'        => 'PHP',
				'javascript' => 'JavaScript',
				'theme-json' => 'theme.json',
				'wp-cli'     => 'WP-CLI',
				'testing'    => 'Testing',
			)
		);

		$post_ids    = $this->ensure_posts( $categories, $tags );
		$template_id = $this->ensure_single_template();

		\WP_CLI::success(
			sprintf(
				/* translators: 1: number of posts, 2: template status. */
				__( 'Prepared %1$d sample posts. Single template: %2$s', 'od-related-query' ),
				count( $post_ids ),
				$template_id ? (string) $template_id : __( 'unchanged', 'od-related-query' )
			)
		);
	}

	/**
	 * Creates named terms and returns their IDs keyed by slug.
	 *
	 * @param string               $taxonomy Taxonomy name.
	 * @param array<string,string> $terms    Slug-to-name map.
	 * @return array<string,int>
	 */
	private function ensure_terms( $taxonomy, $terms ) {
		$term_ids = array();

		foreach ( $terms as $slug => $name ) {
			$existing = term_exists( $slug, $taxonomy );

			if ( $existing ) {
				$term_ids[ $slug ] = (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
				continue;
			}

			$created = wp_insert_term(
				$name,
				$taxonomy,
				array( 'slug' => $slug )
			);

			if ( is_wp_error( $created ) ) {
				\WP_CLI::error( $created->get_error_message() );
			}

			$term_ids[ $slug ] = (int) $created['term_id'];
		}

		return $term_ids;
	}

	/**
	 * Creates or updates the 20 sample posts.
	 *
	 * @param array<string,int> $categories Category IDs.
	 * @param array<string,int> $tags       Tag IDs.
	 * @return array<int,int>
	 */
	private function ensure_posts( $categories, $tags ) {
		$definitions = $this->get_post_definitions();
		$post_ids    = array();
		$base_time   = current_datetime()->getTimestamp();

		foreach ( $definitions as $index => $definition ) {
			$sample_number = $index + 1;
			$existing_ids  = get_posts(
				array(
					'fields'         => 'ids',
					'meta_key'       => self::POST_META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Local seed lookup.
					'meta_value'     => (string) $sample_number, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Local seed lookup.
					'post_status'    => 'any',
					'post_type'      => 'post',
					'posts_per_page' => 1,
				)
			);
			$post_date     = wp_date( 'Y-m-d H:i:s', $base_time - ( $index * DAY_IN_SECONDS ) );
			$post_data     = array(
				'ID'            => $existing_ids ? (int) $existing_ids[0] : 0,
				'post_content'  => $this->get_post_content( $definition['title'] ),
				'post_date'     => $post_date,
				'post_date_gmt' => get_gmt_from_date( $post_date ),
				'post_name'     => sprintf( 'od-related-query-sample-%02d', $sample_number ),
				'post_status'   => 'publish',
				'post_title'    => $definition['title'],
				'post_type'     => 'post',
			);
			$post_id       = wp_insert_post( wp_slash( $post_data ), true );

			if ( is_wp_error( $post_id ) ) {
				\WP_CLI::error( $post_id->get_error_message() );
			}

			update_post_meta( $post_id, self::POST_META_KEY, $sample_number );
			wp_set_post_terms(
				$post_id,
				array_map(
					static function ( $slug ) use ( $categories ) {
						return $categories[ $slug ];
					},
					$definition['categories']
				),
				'category'
			);
			wp_set_post_terms(
				$post_id,
				array_map(
					static function ( $slug ) use ( $tags ) {
						return $tags[ $slug ];
					},
					$definition['tags']
				),
				'post_tag'
			);

			$this->ensure_featured_image( $post_id, $sample_number, $definition['title'] );
			$post_ids[] = $post_id;
		}

		return $post_ids;
	}

	/**
	 * Returns deterministic post fixtures.
	 *
	 * @return array<int,array{title:string,categories:array<int,string>,tags:array<int,string>}>
	 */
	private function get_post_definitions() {
		return array(
			array(
				'title'      => __( 'Understanding the Query Loop block', 'od-related-query' ),
				'categories' => array( 'wordpress', 'block-themes' ),
				'tags'       => array( 'gutenberg', 'query-loop' ),
			),
			array(
				'title'      => __( 'Design notes for a related-content plugin', 'od-related-query' ),
				'categories' => array( 'plugin-development', 'wordpress' ),
				'tags'       => array( 'query-loop', 'taxonomy', 'php' ),
			),
			array(
				'title'      => __( 'Organizing posts with categories', 'od-related-query' ),
				'categories' => array( 'wordpress' ),
				'tags'       => array( 'taxonomy', 'gutenberg' ),
			),
			array(
				'title'      => __( 'Connecting content across the site with tags', 'od-related-query' ),
				'categories' => array( 'wordpress' ),
				'tags'       => array( 'taxonomy', 'query-loop' ),
			),
			array(
				'title'      => __( 'Editing single-post templates in block themes', 'od-related-query' ),
				'categories' => array( 'block-themes' ),
				'tags'       => array( 'gutenberg', 'theme-json' ),
			),
			array(
				'title'      => __( 'Styling post cards with theme.json', 'od-related-query' ),
				'categories' => array( 'block-themes' ),
				'tags'       => array( 'theme-json', 'gutenberg' ),
			),
			array(
				'title'      => __( 'Designing hooks for WordPress plugins', 'od-related-query' ),
				'categories' => array( 'plugin-development' ),
				'tags'       => array( 'php', 'testing' ),
			),
			array(
				'title'      => __( 'When to use the Block Variation API', 'od-related-query' ),
				'categories' => array( 'plugin-development', 'block-themes' ),
				'tags'       => array( 'javascript', 'gutenberg' ),
			),
			array(
				'title'      => __( 'The basics of WP_Query and tax_query', 'od-related-query' ),
				'categories' => array( 'plugin-development' ),
				'tags'       => array( 'php', 'taxonomy' ),
			),
			array(
				'title'      => __( 'Keeping editor previews consistent with the REST API', 'od-related-query' ),
				'categories' => array( 'plugin-development' ),
				'tags'       => array( 'javascript', 'testing' ),
			),
			array(
				'title'      => __( 'Preparing development content with WP-CLI', 'od-related-query' ),
				'categories' => array( 'plugin-development' ),
				'tags'       => array( 'wp-cli', 'testing' ),
			),
			array(
				'title'      => __( 'Writing WordPress integration tests with PHPUnit', 'od-related-query' ),
				'categories' => array( 'plugin-development' ),
				'tags'       => array( 'php', 'testing' ),
			),
			array(
				'title'      => __( 'Testing block extensions with JavaScript', 'od-related-query' ),
				'categories' => array( 'plugin-development' ),
				'tags'       => array( 'javascript', 'testing' ),
			),
			array(
				'title'      => __( 'Choosing the Query Loop item count and order', 'od-related-query' ),
				'categories' => array( 'block-themes', 'performance' ),
				'tags'       => array( 'query-loop', 'gutenberg' ),
			),
			array(
				'title'      => __( 'Measuring taxonomy query load', 'od-related-query' ),
				'categories' => array( 'performance', 'plugin-development' ),
				'tags'       => array( 'taxonomy', 'php' ),
			),
			array(
				'title'      => __( 'What to measure before caching related content', 'od-related-query' ),
				'categories' => array( 'performance' ),
				'tags'       => array( 'query-loop', 'testing' ),
			),
			array(
				'title'      => __( 'Reducing block editor asset size', 'od-related-query' ),
				'categories' => array( 'performance', 'block-themes' ),
				'tags'       => array( 'javascript', 'gutenberg' ),
			),
			array(
				'title'      => __( 'Preparing for custom taxonomy extensions', 'od-related-query' ),
				'categories' => array( 'plugin-development', 'wordpress' ),
				'tags'       => array( 'taxonomy', 'php' ),
			),
			array(
				'title'      => __( 'Adjusting card layouts in the Site Editor', 'od-related-query' ),
				'categories' => array( 'block-themes', 'wordpress' ),
				'tags'       => array( 'theme-json', 'query-loop' ),
			),
			array(
				'title'      => __( 'Related-content display checklist', 'od-related-query' ),
				'categories' => array( 'wordpress', 'performance' ),
				'tags'       => array( 'testing', 'query-loop', 'wp-cli' ),
			),
		);
	}

	/**
	 * Builds simple block content without embedding the related-query block.
	 *
	 * @param string $title Post title.
	 * @return string
	 */
	private function get_post_content( $title ) {
		return sprintf(
			"<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":2} -->\n<h2 class=\"wp-block-heading\">%s</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->",
			esc_html(
				sprintf(
					/* translators: %s: sample post title. */
					__( 'This sample post is used to preview “%s”.', 'od-related-query' ),
					$title
				)
			),
			esc_html__( 'What to check', 'od-related-query' ),
			esc_html__( 'Confirm that posts sharing categories or tags appear in the related-content section of the single-post template.', 'od-related-query' )
		);
	}

	/**
	 * Creates a generated thumbnail when GD is available.
	 *
	 * @param int    $post_id       Parent post ID.
	 * @param int    $sample_number Sample number.
	 * @param string $title         Attachment title.
	 * @return void
	 */
	private function ensure_featured_image( $post_id, $sample_number, $title ) {
		$attachment_ids = get_posts(
			array(
				'fields'         => 'ids',
				'meta_key'       => self::IMAGE_META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Local seed lookup.
				'meta_value'     => (string) $sample_number, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Local seed lookup.
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'posts_per_page' => 1,
			)
		);

		if ( $attachment_ids ) {
			set_post_thumbnail( $post_id, (int) $attachment_ids[0] );
			return;
		}

		if (
			! function_exists( 'imagecreatetruecolor' )
			|| ! function_exists( 'imagepng' )
		) {
			return;
		}

		$image = imagecreatetruecolor( 1200, 675 );

		if ( false === $image ) {
			return;
		}

		$palette = array(
			array( 38, 99, 235 ),
			array( 124, 58, 237 ),
			array( 5, 150, 105 ),
			array( 217, 119, 6 ),
			array( 220, 38, 38 ),
		);
		$color   = $palette[ ( $sample_number - 1 ) % count( $palette ) ];
		$fill    = imagecolorallocate( $image, $color[0], $color[1], $color[2] );

		imagefill( $image, 0, 0, $fill );

		$temporary_file = wp_tempnam( 'od-related-query-sample.png' );

		if ( ! $temporary_file || ! imagepng( $image, $temporary_file ) ) {
			imagedestroy( $image );
			return;
		}

		imagedestroy( $image );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_sideload(
			array(
				'name'     => sprintf( 'od-related-query-sample-%02d.png', $sample_number ),
				'tmp_name' => $temporary_file,
			),
			$post_id,
			$title
		);

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $temporary_file );
			\WP_CLI::warning( $attachment_id->get_error_message() );
			return;
		}

		update_post_meta( $attachment_id, self::IMAGE_META_KEY, $sample_number );
		set_post_thumbnail( $post_id, $attachment_id );
	}

	/**
	 * Adds the related-query section to an uncustomized single template.
	 *
	 * @return int Template post ID, or zero when an existing customization is preserved.
	 */
	private function ensure_single_template() {
		$stylesheet        = get_stylesheet();
		$existing_template = get_posts(
			array(
				'name'           => 'single',
				'post_status'    => 'any',
				'post_type'      => 'wp_template',
				'posts_per_page' => 1,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Finds a local template override.
					array(
						'taxonomy' => 'wp_theme',
						'field'    => 'slug',
						'terms'    => $stylesheet,
					),
				),
			)
		);

		if (
			$existing_template
			&& ! get_post_meta( $existing_template[0]->ID, self::TEMPLATE_META_KEY, true )
		) {
			\WP_CLI::warning(
				__( 'The single template is already customized, so it was not changed.', 'od-related-query' )
			);
			return 0;
		}

		$template_file = get_stylesheet_directory() . '/templates/single.html';

		if ( ! is_readable( $template_file ) ) {
			\WP_CLI::warning(
				__( 'The active theme does not provide templates/single.html.', 'od-related-query' )
			);
			return 0;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a known local theme file.
		$template_content = file_get_contents( $template_file );

		if ( false === $template_content ) {
			return 0;
		}

		$related_section = $this->get_related_template_section();
		$footer_position = strrpos( $template_content, '<!-- wp:template-part {"slug":"footer"' );

		if ( false === $footer_position ) {
			$template_content .= "\n\n" . $related_section;
		} else {
			$template_content =
				substr( $template_content, 0, $footer_position )
				. $related_section
				. "\n\n"
				. substr( $template_content, $footer_position );
		}

		$template_id = wp_insert_post(
			wp_slash(
				array(
					'ID'           => $existing_template ? $existing_template[0]->ID : 0,
					'post_content' => $template_content,
					'post_name'    => 'single',
					'post_status'  => 'publish',
					'post_title'   => __( 'Single Posts', 'od-related-query' ),
					'post_type'    => 'wp_template',
				)
			),
			true
		);

		if ( is_wp_error( $template_id ) ) {
			\WP_CLI::error( $template_id->get_error_message() );
		}

		wp_set_object_terms( $template_id, $stylesheet, 'wp_theme' );
		update_post_meta( $template_id, self::TEMPLATE_META_KEY, 1 );

		return $template_id;
	}

	/**
	 * Returns the related-query block markup used by the sample template.
	 *
	 * @return string
	 */
	private function get_related_template_section() {
		$template = <<<'HTML'
<!-- wp:group {"tagName":"section","metadata":{"name":"OD Related Query Sample"},"layout":{"type":"constrained"}} -->
<section class="wp-block-group"><!-- wp:heading -->
<h2 class="wp-block-heading">%1$s</h2>
<!-- /wp:heading -->

<!-- wp:query {"query":{"perPage":3,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false,"od_related_to":0},"namespace":"od-related-query/related"} -->
<div class="wp-block-query"><!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} -->
<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"3/2"} /-->

<!-- wp:post-title {"isLink":true,"level":3} /-->

<!-- wp:post-date /-->
<!-- /wp:post-template -->

<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p>%2$s</p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query --></section>
<!-- /wp:group -->
HTML;

		return sprintf(
			$template,
			esc_html__( 'Related content', 'od-related-query' ),
			esc_html__( 'No related content found.', 'od-related-query' )
		);
	}
}
