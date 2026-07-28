import {
	getExcludedTaxonomySlugs,
	getPreviewSourcePostId,
	getSelectedTaxonomySlugs,
	getTaxonomySourcePostType,
	RELATED_EXCLUDED_TAXONOMIES_PARAMETER,
	RELATED_ORDERBY_PARAMETER,
	RELATED_POST_PARAMETER,
	RELATED_QUERY_VARIATION,
	RELATED_TAXONOMIES_PARAMETER,
	VARIATION_NAMESPACE,
} from './variation';

describe( 'related Query Loop variation', () => {
	it( 'uses a scoped namespace and safe query defaults', () => {
		expect( RELATED_QUERY_VARIATION.name ).toBe( VARIATION_NAMESPACE );
		expect( RELATED_QUERY_VARIATION.attributes.namespace ).toBe(
			VARIATION_NAMESPACE
		);
		expect( RELATED_QUERY_VARIATION.attributes.query ).toMatchObject( {
			perPage: 3,
			postType: 'post',
			inherit: false,
			[ RELATED_POST_PARAMETER ]: 0,
			[ RELATED_ORDERBY_PARAMETER ]: 'date',
			[ RELATED_EXCLUDED_TAXONOMIES_PARAMETER ]: [],
		} );
		expect( RELATED_QUERY_VARIATION.allowedControls ).toEqual( [
			'postType',
			'order',
			'postCount',
		] );
	} );

	it( 'starts with an editable three-column post template', () => {
		const [ postTemplate ] = RELATED_QUERY_VARIATION.innerBlocks;

		expect( postTemplate[ 0 ] ).toBe( 'core/post-template' );
		expect( postTemplate[ 1 ].layout ).toEqual( {
			type: 'grid',
			columnCount: 3,
		} );
		expect( postTemplate[ 2 ].map( ( block ) => block[ 0 ] ) ).toEqual( [
			'core/post-featured-image',
			'core/post-title',
			'core/post-date',
		] );
	} );

	it( 'selects every available taxonomy by default', () => {
		const taxonomies = [
			{ slug: 'category' },
			{ slug: 'post_tag' },
			{ slug: 'topic' },
		];

		expect( getSelectedTaxonomySlugs( taxonomies ) ).toEqual( [
			'category',
			'post_tag',
			'topic',
		] );
	} );

	it( 'keeps newly added taxonomies selected unless explicitly excluded', () => {
		const query = {
			[ RELATED_EXCLUDED_TAXONOMIES_PARAMETER ]: [ 'post_tag' ],
		};
		const taxonomies = [
			{ slug: 'category' },
			{ slug: 'post_tag' },
			{ slug: 'new_topic' },
		];

		expect( getSelectedTaxonomySlugs( taxonomies, query ) ).toEqual( [
			'category',
			'new_topic',
		] );
	} );

	it( 'supports selected taxonomies stored by previous versions', () => {
		const query = {
			[ RELATED_TAXONOMIES_PARAMETER ]: [ 'post_tag' ],
		};
		const taxonomies = [ { slug: 'category' }, { slug: 'post_tag' } ];

		expect( getSelectedTaxonomySlugs( taxonomies, query ) ).toEqual( [
			'post_tag',
		] );
	} );

	it( 'stores every unchecked available taxonomy as excluded', () => {
		const taxonomies = [
			{ slug: 'category' },
			{ slug: 'post_tag' },
			{ slug: 'topic' },
		];

		expect(
			getExcludedTaxonomySlugs( taxonomies, [ 'category' ] )
		).toEqual( [ 'post_tag', 'topic' ] );
	} );

	it( 'uses the edited post type in the post editor', () => {
		expect( getTaxonomySourcePostType( 'book', 'post' ) ).toBe( 'book' );
	} );

	it( 'uses the Query Loop post type in the template editor', () => {
		expect( getTaxonomySourcePostType( 'wp_template', 'post' ) ).toBe(
			'post'
		);
		expect( getTaxonomySourcePostType( 'wp_template', 'book' ) ).toBe(
			'book'
		);
	} );

	it( 'does not use internal editor entities as taxonomy sources', () => {
		expect( getTaxonomySourcePostType( 'wp_template' ) ).toBeUndefined();
		expect(
			getTaxonomySourcePostType( 'wp_template', 'wp_template' )
		).toBeUndefined();
	} );

	it( 'keeps an available template preview source selected', () => {
		const posts = [ { id: 12 }, { id: 34 } ];

		expect( getPreviewSourcePostId( posts, 34 ) ).toBe( 34 );
	} );

	it( 'automatically selects a valid template preview source', () => {
		expect( getPreviewSourcePostId( [ { id: 12 }, { id: 34 } ], 99 ) ).toBe(
			12
		);
		expect( getPreviewSourcePostId( [], 99 ) ).toBe( 0 );
	} );
} );
