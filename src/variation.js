import { __ } from '@wordpress/i18n';

export const VARIATION_NAMESPACE = 'od-related-query/related';
export const RELATED_POST_PARAMETER = 'od_related_to';
export const RELATED_TAXONOMIES_PARAMETER = 'od_related_taxonomies';
export const RELATED_EXCLUDED_TAXONOMIES_PARAMETER =
	'od_related_taxonomies_excluded';

/**
 * Returns the post type whose taxonomies should be shown in the editor.
 *
 * A template is an editor entity rather than a content type. In that context,
 * the Query Loop's selected target post type supplies the taxonomy candidates.
 *
 * @param {string|undefined} editorPostType Current editor entity post type.
 * @param {string|undefined} queryPostType  Query Loop target post type.
 * @return {string|undefined} Taxonomy source post type.
 */
export function getTaxonomySourcePostType( editorPostType, queryPostType ) {
	if ( 'wp_template' === editorPostType ) {
		return 'string' === typeof queryPostType &&
			queryPostType &&
			! queryPostType.startsWith( 'wp_' )
			? queryPostType
			: undefined;
	}

	return 'string' === typeof editorPostType &&
		editorPostType &&
		! editorPostType.startsWith( 'wp_' )
		? editorPostType
		: undefined;
}

/**
 * Returns the taxonomy slugs selected by the current query configuration.
 *
 * The excluded-taxonomies setting takes precedence. The older selected-
 * taxonomies setting remains supported for blocks created by previous versions.
 *
 * @param {Array}  availableTaxonomies Available taxonomy records.
 * @param {Object} query               Query Loop query attributes.
 * @return {string[]} Selected taxonomy slugs.
 */
export function getSelectedTaxonomySlugs( availableTaxonomies, query = {} ) {
	const availableSlugs = availableTaxonomies
		.map( ( taxonomy ) => taxonomy.slug )
		.filter( Boolean );
	const excludedTaxonomies = query[ RELATED_EXCLUDED_TAXONOMIES_PARAMETER ];

	if ( Array.isArray( excludedTaxonomies ) ) {
		return availableSlugs.filter(
			( slug ) => ! excludedTaxonomies.includes( slug )
		);
	}

	const legacySelectedTaxonomies = query[ RELATED_TAXONOMIES_PARAMETER ];

	if (
		Array.isArray( legacySelectedTaxonomies ) &&
		legacySelectedTaxonomies.length
	) {
		return availableSlugs.filter( ( slug ) =>
			legacySelectedTaxonomies.includes( slug )
		);
	}

	return availableSlugs;
}

/**
 * Returns the available taxonomy slugs that are not selected.
 *
 * @param {Array}    availableTaxonomies Available taxonomy records.
 * @param {string[]} selectedTaxonomies  Selected taxonomy slugs.
 * @return {string[]} Excluded taxonomy slugs.
 */
export function getExcludedTaxonomySlugs(
	availableTaxonomies,
	selectedTaxonomies
) {
	return availableTaxonomies
		.map( ( taxonomy ) => taxonomy.slug )
		.filter( ( slug ) => slug && ! selectedTaxonomies.includes( slug ) );
}

export const RELATED_QUERY_VARIATION = {
	name: VARIATION_NAMESPACE,
	title: __( 'Related Content', 'od-related-query' ),
	description: __(
		'Displays content that shares a category, tag, or public custom taxonomy term with the current post.',
		'od-related-query'
	),
	icon: 'admin-links',
	keywords: [
		__( 'related', 'od-related-query' ),
		__( 'posts', 'od-related-query' ),
	],
	attributes: {
		namespace: VARIATION_NAMESPACE,
		query: {
			perPage: 3,
			pages: 0,
			offset: 0,
			postType: 'post',
			order: 'desc',
			orderBy: 'date',
			author: '',
			search: '',
			exclude: [],
			sticky: '',
			inherit: false,
			[ RELATED_POST_PARAMETER ]: 0,
			[ RELATED_EXCLUDED_TAXONOMIES_PARAMETER ]: [],
		},
	},
	allowedControls: [ 'postType', 'order', 'postCount' ],
	scope: [ 'inserter' ],
	isActive: [ 'namespace' ],
	innerBlocks: [
		[
			'core/post-template',
			{
				layout: {
					type: 'grid',
					columnCount: 3,
				},
			},
			[
				[
					'core/post-featured-image',
					{
						isLink: true,
						aspectRatio: '3/2',
					},
				],
				[
					'core/post-title',
					{
						isLink: true,
						level: 3,
					},
				],
				[ 'core/post-date' ],
			],
		],
		[
			'core/query-no-results',
			{},
			[
				[
					'core/paragraph',
					{
						content: __(
							'No related content found.',
							'od-related-query'
						),
					},
				],
			],
		],
	],
};
