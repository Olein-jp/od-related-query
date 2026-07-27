import { __ } from '@wordpress/i18n';

export const VARIATION_NAMESPACE = 'od-related-query/related';
export const RELATED_POST_PARAMETER = 'od_related_to';

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
		},
	},
	allowedControls: [ 'order' ],
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
