import {
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
			[ RELATED_TAXONOMIES_PARAMETER ]: [],
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
} );
