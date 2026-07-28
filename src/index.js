import { registerBlockVariation } from '@wordpress/blocks';
import { InspectorControls } from '@wordpress/block-editor';
import { createHigherOrderComponent } from '@wordpress/compose';
import { CheckboxControl, Notice, PanelBody } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { createElement, Fragment, useEffect } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

import {
	getExcludedTaxonomySlugs,
	getSelectedTaxonomySlugs,
	RELATED_EXCLUDED_TAXONOMIES_PARAMETER,
	RELATED_POST_PARAMETER,
	RELATED_QUERY_VARIATION,
	RELATED_TAXONOMIES_PARAMETER,
	VARIATION_NAMESPACE,
} from './variation';

registerBlockVariation( 'core/query', RELATED_QUERY_VARIATION );

function RelatedQueryBlockEdit( { BlockEdit, ...props } ) {
	const { attributes, setAttributes } = props;
	const { query = {} } = attributes;
	const editorContext = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		const core = select( 'core' );
		const postType = editor?.getCurrentPostType();

		return {
			postId: editor?.getCurrentPostId(),
			postType,
			taxonomies:
				'string' === typeof postType
					? core?.getTaxonomies( {
							type: postType,
							per_page: -1,
					  } )
					: [],
		};
	}, [] );
	const sourcePostId = Number( editorContext.postId );
	const hasUsablePostContext =
		Number.isInteger( sourcePostId ) &&
		0 < sourcePostId &&
		'string' === typeof editorContext.postType &&
		! editorContext.postType.startsWith( 'wp_' );
	const availableTaxonomies = Array.isArray( editorContext.taxonomies )
		? editorContext.taxonomies.filter(
				( taxonomy ) => false !== taxonomy.visibility?.public
		  )
		: [];
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
				...availableTaxonomies.map( ( taxonomy ) =>
					createElement( CheckboxControl, {
						key: taxonomy.slug,
						label: taxonomy.name,
						checked: selectedTaxonomies.includes( taxonomy.slug ),
						onChange: ( isSelected ) =>
							setTaxonomySelected( taxonomy.slug, isSelected ),
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
