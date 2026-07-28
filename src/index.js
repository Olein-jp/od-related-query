import { registerBlockVariation } from '@wordpress/blocks';
import { InspectorControls } from '@wordpress/block-editor';
import { createHigherOrderComponent } from '@wordpress/compose';
import {
	CheckboxControl,
	Notice,
	PanelBody,
	SelectControl,
	Spinner,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { createElement, Fragment, useEffect } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

import {
	getExcludedTaxonomySlugs,
	getPreviewSourcePostId,
	getSelectedTaxonomySlugs,
	getTaxonomySourcePostType,
	RELATED_EXCLUDED_TAXONOMIES_PARAMETER,
	RELATED_POST_PARAMETER,
	RELATED_PREVIEW_POST_PARAMETER,
	RELATED_QUERY_VARIATION,
	RELATED_TAXONOMIES_PARAMETER,
	VARIATION_NAMESPACE,
} from './variation';

registerBlockVariation( 'core/query', RELATED_QUERY_VARIATION );

function getNoTaxonomiesMessage( sourcePostType, targetPostType ) {
	if ( ! sourcePostType ) {
		return __(
			'Select a post type in the Query settings to load its taxonomies.',
			'od-related-query'
		);
	}

	if ( sourcePostType !== targetPostType ) {
		return __(
			'The source and target post types do not share any public taxonomies. Related content cannot be determined for this combination.',
			'od-related-query'
		);
	}

	return __(
		'No public taxonomies are available for the selected post type.',
		'od-related-query'
	);
}

function RelatedQueryBlockEdit( { BlockEdit, ...props } ) {
	const { attributes, setAttributes } = props;
	const { query = {} } = attributes;
	const editorContext = useSelect( ( select ) => {
		const editor = select( 'core/editor' );

		return {
			postId: editor?.getCurrentPostId(),
			postType: editor?.getCurrentPostType(),
		};
	}, [] );
	const taxonomySourcePostType = getTaxonomySourcePostType(
		editorContext.postType,
		query.postType
	);
	const taxonomyTargetPostType =
		'string' === typeof query.postType &&
		query.postType &&
		! query.postType.startsWith( 'wp_' )
			? query.postType
			: taxonomySourcePostType;
	const isTemplateEditor = 'wp_template' === editorContext.postType;
	const taxonomyContext = useSelect(
		( select ) => {
			if ( ! taxonomySourcePostType || ! taxonomyTargetPostType ) {
				return {
					error: null,
					isLoading: false,
					sourceTaxonomies: [],
					targetTaxonomies: [],
				};
			}

			const core = select( 'core' );
			const sourceQuery = {
				type: taxonomySourcePostType,
				per_page: -1,
			};
			const targetQuery = {
				type: taxonomyTargetPostType,
				per_page: -1,
			};
			const sourceResolutionArgs = [ sourceQuery ];
			const targetResolutionArgs = [ targetQuery ];
			const sourceTaxonomies = core.getTaxonomies( sourceQuery );
			const targetTaxonomies =
				taxonomySourcePostType === taxonomyTargetPostType
					? sourceTaxonomies
					: core.getTaxonomies( targetQuery );

			return {
				error:
					core.getResolutionError(
						'getTaxonomies',
						sourceResolutionArgs
					) ||
					( taxonomySourcePostType !== taxonomyTargetPostType
						? core.getResolutionError(
								'getTaxonomies',
								targetResolutionArgs
						  )
						: null ),
				isLoading:
					! core.hasFinishedResolution(
						'getTaxonomies',
						sourceResolutionArgs
					) ||
					( taxonomySourcePostType !== taxonomyTargetPostType &&
						! core.hasFinishedResolution(
							'getTaxonomies',
							targetResolutionArgs
						) ),
				sourceTaxonomies,
				targetTaxonomies,
			};
		},
		[ taxonomySourcePostType, taxonomyTargetPostType ]
	);
	const previewSourceContext = useSelect(
		( select ) => {
			if ( ! isTemplateEditor || ! taxonomySourcePostType ) {
				return {
					error: null,
					isLoading: false,
					posts: [],
				};
			}

			const core = select( 'core' );
			const postsQuery = {
				per_page: 20,
				status: 'publish',
				order: 'desc',
				orderby: 'date',
				_fields: [ 'id', 'title' ],
			};
			const resolutionArgs = [
				'postType',
				taxonomySourcePostType,
				postsQuery,
			];

			return {
				error: core.getResolutionError(
					'getEntityRecords',
					resolutionArgs
				),
				isLoading: ! core.hasFinishedResolution(
					'getEntityRecords',
					resolutionArgs
				),
				posts:
					core.getEntityRecords(
						'postType',
						taxonomySourcePostType,
						postsQuery
					) || [],
			};
		},
		[ isTemplateEditor, taxonomySourcePostType ]
	);
	const sourcePostId = Number( editorContext.postId );
	const hasUsablePostContext =
		Number.isInteger( sourcePostId ) &&
		0 < sourcePostId &&
		'string' === typeof editorContext.postType &&
		! editorContext.postType.startsWith( 'wp_' );
	const sourceTaxonomies = Array.isArray( taxonomyContext.sourceTaxonomies )
		? taxonomyContext.sourceTaxonomies.filter(
				( taxonomy ) => false !== taxonomy.visibility?.public
		  )
		: [];
	const targetTaxonomySlugs = new Set(
		Array.isArray( taxonomyContext.targetTaxonomies )
			? taxonomyContext.targetTaxonomies
					.filter(
						( taxonomy ) => false !== taxonomy.visibility?.public
					)
					.map( ( taxonomy ) => taxonomy.slug )
			: []
	);
	const availableTaxonomies = sourceTaxonomies.filter( ( taxonomy ) =>
		targetTaxonomySlugs.has( taxonomy.slug )
	);
	const selectedTaxonomies = getSelectedTaxonomySlugs(
		availableTaxonomies,
		query
	);

	useEffect( () => {
		if ( ! hasUsablePostContext ) {
			return;
		}

		if ( sourcePostId === query[ RELATED_POST_PARAMETER ] ) {
			return;
		}

		const nextQuery = {
			...query,
			[ RELATED_POST_PARAMETER ]: sourcePostId,
		};

		/*
		 * Use the current post type as the initial target. Once the source
		 * context has been recorded, preserve any post type the user selects
		 * with the Query Loop's standard control.
		 */
		if ( ! query[ RELATED_POST_PARAMETER ] ) {
			nextQuery.postType = editorContext.postType;
		}

		setAttributes( {
			query: nextQuery,
		} );
	}, [
		editorContext.postType,
		hasUsablePostContext,
		query,
		setAttributes,
		sourcePostId,
	] );

	useEffect( () => {
		if (
			! isTemplateEditor ||
			previewSourceContext.isLoading ||
			previewSourceContext.error
		) {
			return;
		}

		const previewPostId = getPreviewSourcePostId(
			previewSourceContext.posts,
			query[ RELATED_PREVIEW_POST_PARAMETER ]
		);

		if ( previewPostId === query[ RELATED_PREVIEW_POST_PARAMETER ] ) {
			return;
		}

		setAttributes( {
			query: {
				...query,
				[ RELATED_PREVIEW_POST_PARAMETER ]: previewPostId,
			},
		} );
	}, [
		isTemplateEditor,
		previewSourceContext.error,
		previewSourceContext.isLoading,
		previewSourceContext.posts,
		query,
		setAttributes,
	] );

	useEffect( () => {
		if ( taxonomyContext.isLoading || taxonomyContext.error ) {
			return;
		}

		const availableSlugs = new Set(
			availableTaxonomies.map( ( taxonomy ) => taxonomy.slug )
		);
		const settingKeys = [
			RELATED_EXCLUDED_TAXONOMIES_PARAMETER,
			RELATED_TAXONOMIES_PARAMETER,
		];
		const settingKey = settingKeys.find( ( key ) =>
			Array.isArray( query[ key ] )
		);

		if ( ! settingKey ) {
			return;
		}

		const storedTaxonomies = query[ settingKey ];
		const validTaxonomies = storedTaxonomies.filter( ( slug ) =>
			availableSlugs.has( slug )
		);

		if (
			validTaxonomies.length === storedTaxonomies.length &&
			validTaxonomies.every(
				( slug, index ) => slug === storedTaxonomies[ index ]
			)
		) {
			return;
		}

		setAttributes( {
			query: {
				...query,
				[ settingKey ]: validTaxonomies,
			},
		} );
	}, [
		availableTaxonomies,
		query,
		setAttributes,
		taxonomyContext.error,
		taxonomyContext.isLoading,
	] );

	const setPreviewSourcePost = ( previewPostId ) => {
		setAttributes( {
			query: {
				...query,
				[ RELATED_PREVIEW_POST_PARAMETER ]: Number( previewPostId ),
			},
		} );
	};

	const setTaxonomySelected = ( taxonomySlug, isSelected ) => {
		const nextTaxonomies = isSelected
			? [ ...selectedTaxonomies, taxonomySlug ]
			: selectedTaxonomies.filter( ( slug ) => slug !== taxonomySlug );
		const nextQuery = {
			...query,
			[ RELATED_EXCLUDED_TAXONOMIES_PARAMETER ]: getExcludedTaxonomySlugs(
				availableTaxonomies,
				[ ...new Set( nextTaxonomies ) ]
			),
		};

		// Migrate an edited legacy block to exclusion-based storage.
		delete nextQuery[ RELATED_TAXONOMIES_PARAMETER ];
		setAttributes( {
			query: nextQuery,
		} );
	};

	return createElement(
		Fragment,
		null,
		createElement( BlockEdit, props ),
		createElement(
			InspectorControls,
			null,
			createElement(
				PanelBody,
				{
					title: __( 'Related content', 'od-related-query' ),
					initialOpen: true,
				},
				createElement(
					'p',
					null,
					__(
						'Choose the taxonomies used to find shared terms. New public taxonomies are selected automatically.',
						'od-related-query'
					)
				),
				isTemplateEditor &&
					previewSourceContext.isLoading &&
					createElement(
						'p',
						{
							'aria-live': 'polite',
						},
						createElement( Spinner ),
						__(
							'Loading preview source posts…',
							'od-related-query'
						)
					),
				isTemplateEditor &&
					! previewSourceContext.isLoading &&
					previewSourceContext.error &&
					createElement(
						Notice,
						{
							status: 'error',
							isDismissible: false,
						},
						__(
							'Preview source posts could not be loaded.',
							'od-related-query'
						)
					),
				isTemplateEditor &&
					! previewSourceContext.isLoading &&
					! previewSourceContext.error &&
					0 === previewSourceContext.posts.length &&
					createElement(
						Notice,
						{
							status: 'warning',
							isDismissible: false,
						},
						__(
							'No published posts are available for this post type. Publish a post to preview related content in the template editor.',
							'od-related-query'
						)
					),
				isTemplateEditor &&
					! previewSourceContext.isLoading &&
					! previewSourceContext.error &&
					0 < previewSourceContext.posts.length &&
					createElement( SelectControl, {
						label: __( 'Preview source post', 'od-related-query' ),
						help: __(
							'Used only for the template editor preview. The frontend always uses the post being viewed.',
							'od-related-query'
						),
						value: String(
							getPreviewSourcePostId(
								previewSourceContext.posts,
								query[ RELATED_PREVIEW_POST_PARAMETER ]
							)
						),
						options: previewSourceContext.posts.map( ( post ) => ( {
							label:
								post.title?.rendered ||
								post.title?.raw ||
								__( '(Untitled)', 'od-related-query' ),
							value: String( post.id ),
						} ) ),
						onChange: setPreviewSourcePost,
					} ),
				taxonomyContext.isLoading &&
					createElement(
						'p',
						{
							'aria-live': 'polite',
						},
						createElement( Spinner ),
						__(
							'Loading available taxonomies…',
							'od-related-query'
						)
					),
				! taxonomyContext.isLoading &&
					taxonomyContext.error &&
					createElement(
						Notice,
						{
							status: 'error',
							isDismissible: false,
						},
						__(
							'The available taxonomies could not be loaded.',
							'od-related-query'
						)
					),
				! taxonomyContext.isLoading &&
					! taxonomyContext.error &&
					0 === availableTaxonomies.length &&
					createElement(
						Notice,
						{
							status: 'info',
							isDismissible: false,
						},
						getNoTaxonomiesMessage(
							taxonomySourcePostType,
							taxonomyTargetPostType
						)
					),
				! taxonomyContext.isLoading &&
					! taxonomyContext.error &&
					availableTaxonomies.map( ( taxonomy ) =>
						createElement( CheckboxControl, {
							key: taxonomy.slug,
							label: taxonomy.name,
							checked: selectedTaxonomies.includes(
								taxonomy.slug
							),
							onChange: ( isSelected ) =>
								setTaxonomySelected(
									taxonomy.slug,
									isSelected
								),
						} )
					),
				0 < availableTaxonomies.length &&
					0 === selectedTaxonomies.length &&
					createElement(
						Notice,
						{
							status: 'warning',
							isDismissible: false,
						},
						__(
							'Select at least one taxonomy to display related content. With no taxonomies selected, this Query Loop returns no results.',
							'od-related-query'
						)
					)
			)
		)
	);
}

const withRelatedQueryContext = createHigherOrderComponent(
	( BlockEdit ) =>
		function RelatedQueryContextWrapper( props ) {
			const isRelatedQuery =
				VARIATION_NAMESPACE === props.attributes?.namespace;

			if ( ! isRelatedQuery ) {
				return createElement( BlockEdit, props );
			}

			return createElement( RelatedQueryBlockEdit, {
				BlockEdit,
				...props,
			} );
		},
	'withRelatedQueryContext'
);

addFilter(
	'editor.BlockEdit',
	'od-related-query/with-related-query-context',
	withRelatedQueryContext
);
